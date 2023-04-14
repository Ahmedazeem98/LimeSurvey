<?php
require_once __DIR__ . '/vendor/autoload.php';

use Greew\OAuth2\Client\Provider\Azure;
use LimeSurvey\PluginManager\SmtpOauthPluginBase;
use PHPMailer\PHPMailer\PHPMailer;

class AzureOAuthSMTP extends SmtpOauthPluginBase
{
    protected $storage = 'DbStorage';
    protected static $description = 'Core: Adds Azure OAuth support for email sending';
    protected static $name = 'AzureOAuthSMTP';

    /** @inheritdoc, this plugin doesn't have any public method */
    public $allowedPublicMethods = [];

    /** @inheritdoc */
    protected $credentialAttributes = ['clientId', 'clientSecret', 'tenantId'];

    protected $settings = [
        'help' => [
            'type' => 'info',
            'content' => '',
        ],
        'clientId' => [
            'type' => 'string',
            'label' => 'Client ID',
        ],
        'clientSecret' => [
            'type' => 'string',
            'label' => 'Client Secret',
        ],
        'tenantId' => [
            'type' => 'string',
            'label' => 'Tenant ID',
        ],
    ];

    public function init()
    {
        $this->subscribe('listSMTPOAuthPlugins');
        $this->subscribe('afterSelectSMTPOAuthPlugin');
        $this->subscribe('newSMTPOAuthConfiguration');
        $this->subscribe('beforeRedirectToAuthPage');
        $this->subscribe('beforePrepareRedirectToAuthPage');

        $this->subscribe('beforeEmail');
        $this->subscribe('beforeSurveyEmail', 'beforeEmail');
        $this->subscribe('beforeTokenEmail', 'beforeEmail');
    }

    /**
     * @inheritdoc
     * Update the information content
     */
    public function getPluginSettings($getValues = true)
    {
        $settings = parent::getPluginSettings($getValues);
        $settings['help']['content'] = $this->getHelpContent();
        $settings['clientId']['label'] = gT("Client ID");
        $settings['clientSecret']['label'] = gT("Client Secret");
        $settings['tenantId']['label'] = gT("Tenant ID");

        $currentEmailSetting = $this->getCurrentEmailSetting();
        if (!empty($currentEmailSetting)) {
            $settings['currentEmail'] = $currentEmailSetting;
        }

        return $settings;
    }

    private function getHelpContent()
    {
        $this->subscribe('getPluginTwigPath');
        $data = [
            'redirectUri' => $this->getRedirectUri(),
            'isHttp' => !(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        ];
        return Yii::app()->twigRenderer->renderPartial('/Help.twig', $data);

        // Translations here just so the translations bot can pick them up.
        $lang = [
            gT("Help"),
            gT("Prerequisites:"),
            gT("Access LimeSurvey over HTTPS."),
            gT("Currently not served over HTTPS"),
            gT("Instructions:"),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getProvider($credentials)
    {
        $redirectUri = $this->getRedirectUri();
        $params = [
            'clientId' => $credentials['clientId'],
            'clientSecret' => $credentials['clientSecret'],
            'tenantId' => $credentials['tenantId'],
            'redirectUri' => $redirectUri,
            'accessType' => 'offline',
            'prompt' => 'consent',
        ];
        return new Azure($params);
    }

    /**
     * Adds view's path to twig system
     */
    public function getPluginTwigPath()
    {
        $viewPath = dirname(__FILE__) . "/views";
        $this->getEvent()->append('add', array($viewPath));
    }

    /**
     * Adds the plugin to the list of SMTP OAuth plugins
     */
    public function listSMTPOAuthPlugins()
    {
        $event = $this->getEvent();
        $event->append('oauthplugins', [
            'azure' => $this->getOwnMetadata()
        ]);
    }

    public function newSMTPOAuthConfiguration()
    {
        try {
            $credentials = $this->getCredentials();
            $clientId = $credentials['clientId'];
            $clientSecret = $credentials['clientSecret'];
        } catch (Exception $ex) {
            $this->log("AzureOAuthSMTP is enabled but credentials are incomplete.", CLogger::LEVEL_WARNING);
            return;
        }

        $refreshToken = $this->get("refreshToken");
        if (empty($refreshToken)) {
            $this->log("AzureOAuthSMTP is enabled but there is no refresh token stored.", CLogger::LEVEL_WARNING);
            return;
        }

        $emailAddress = $this->get("email");
        if (empty($emailAddress)) {
            $this->log("AzureOAuthSMTP is enabled but there is no email address. Please generate a new token.", CLogger::LEVEL_WARNING);
            return;
        }

        $event = $this->getEvent();

        $provider = $this->getProvider($credentials);
        $config = [
            'provider' => $provider,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
            'userName' => $emailAddress,
        ];

        $event->set('oauthconfig', $config);

        $limeMailer = $event->get('mailer');
        $limeMailer->Host = 'smtp.office365.com';
        $limeMailer->Port = 587;
        $limeMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    /**
     * @inheritdoc
     */
    protected function getAuthorizationOptions()
    {
        return [
            'scope' => [
                'https://outlook.office.com/SMTP.Send openid email offline_access ',
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeEmail()
    {
        // Don't do anything if the current plugin is not the one selected.
        if (!$this->isCurrentSMTPOAuthHandler()) {
            return;
        }

        $event = $this->getEvent();
        $limeMailer = $event->get('mailer');
        // Set "Reply To" because we need to override the From/Sender with the logged user.
        $limeMailer->AddReplyTo($limeMailer->From, $limeMailer->FromName);

        // Override the sender to avoid to match OAuth credentials.
        $from = $this->get('email');
        $event->set('from', $from);
    }

    protected function afterRefreshTokenRetrieved($provider, $token)
    {
        $tokenValues = $token->getValues();
        if (empty($tokenValues['id_token'])) {
            throw new Exception("The token doesn't contain an id_token. This is required to get the user's email address.");
        }
        $idToken = $tokenValues['id_token'];
        // TODO: Decode the token using the public key from the provider. Could use firebase/php-jwt.
        // That would require getting the public key from the provider. See: https://github.com/TheNetworg/oauth2-azure/blob/dc095e5a6ae485be8a0c8b88a0d07616c18d484b/src/Provider/Azure.php#L376
        $idTokenParts = explode('.', $idToken);
        $idTokenPayload = $idTokenParts[1];
        $decodedToken = json_decode(base64_decode($idTokenPayload));
        $email = $decodedToken->email;
        $this->set('email', $email);
    }

     /**
     * @inheritdoc
     */
    protected function getProviderName()
    {
        return gT('Azure');
    }

    public function beforePrepareRedirectToAuthPage()
    {
        $event = $this->getEvent();
        $event->set('width', 600);
        $event->set('height', 700);
        $event->set('providerName', $this->getProviderName());
    }
}
