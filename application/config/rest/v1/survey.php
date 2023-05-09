<?php

use \LimeSurvey\Api\Command\V1\{
    SurveyList,
    SurveyDetail,
    SurveyPatch
};
use LimeSurvey\Api\Rest\V1\SchemaFactory\{
    SchemaFactoryError,
    SchemaFactorySurveyList,
    SchemaFactorySurveyDetail,
    SchemaFactorySurveyPatch
};

use GoldSpecDigital\ObjectOrientedOAS\Objects\Example;

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REST V2 Survey Config
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$errorSchema = (new SchemaFactoryError)->create();
$surveyPatchSchema = (new SchemaFactorySurveyPatch)->create();

$rest = [];

$rest['v1/survey'] = [
    'GET' => [
        'description' => 'Survey list',
        'commandClass' => SurveyList::class,
        'auth' => 'session',
        'params' => [
            'pageSize' => ['type' => 'int'],
            'page' => ['type' => 'int']
        ],
        'responses' => [
            'success' => [
                'code' => 200,
                'description' => 'Success',
                'content' => null,
                'schema' => (new SchemaFactorySurveyList)->create()
            ],
            'unauthorized' => [
                'code' => 401,
                'description' => 'Unauthorized',
                'schema' => $errorSchema
            ]
        ]
    ]
];

$rest['v1/survey-detail/$id'] = [
    'GET' => [
        'tag' => 'survey',
        'description' => 'Survey detail',
        'commandClass' => SurveyDetail::class,
        'auth' => 'session',
        'responses' => [
            'success' => [
                'code' => 200,
                'description' => 'Success',
                'content' => null,
                'schema' => (new SchemaFactorySurveyDetail)->create()
            ],
            'unauthorized' => [
                'code' => 401,
                'description' => 'Unauthorized',
                'schema' => $errorSchema
            ],
            'not-found' => [
                'code' => 404,
                'description' => 'Not Found',
                'schema' => $errorSchema
            ]
        ]
    ],
    'PATCH' => [
        'tag' => 'survey',
        'description' => 'Survey update via RFC 6902 based patch',
        'commandClass' => SurveyPatch::class,
        'auth' => 'session',
        'example' => __DIR__ . '/example/survey-patch-all.json',
        'schema' => $surveyPatchSchema,
        'responses' => [
            'success' => [
                'code' => 200,
                'description' => 'Success',
                'examples' => null,
                'content' => null,
                'schema' => null
            ],
            'unauthorized' => [
                'code' => 401,
                'description' => 'Unauthorized',
                'schema' => $errorSchema
            ],
            'not-found' => [
                'code' => 404,
                'description' => 'Not Found',
                'schema' => $errorSchema
            ]
        ]
    ]
];

return $rest;
