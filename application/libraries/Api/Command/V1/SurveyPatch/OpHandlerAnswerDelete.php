<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch;

use LimeSurvey\Api\Command\V1\SurveyPatch\Traits\OpHandlerSurveyTrait;
use LimeSurvey\Models\Services\{
    Exception\PermissionDeniedException,
    QuestionAggregateService
};
use LimeSurvey\Api\Command\V1\SurveyPatch\Traits\OpHandlerValidationTrait;
use LimeSurvey\ObjectPatch\{
    Op\OpInterface,
    OpHandler\OpHandlerException,
    OpHandler\OpHandlerInterface,
    OpType\OpTypeDelete
};

class OpHandlerAnswerDelete implements OpHandlerInterface
{
    use OpHandlerSurveyTrait;
    use OpHandlerValidationTrait;

    protected QuestionAggregateService $questionAggregateService;

    /**
     * @param QuestionAggregateService $questionAggregateService
     */
    public function __construct(
        QuestionAggregateService $questionAggregateService
    ) {
        $this->questionAggregateService = $questionAggregateService;
    }

    /**
     * @param OpInterface $op
     * @return bool
     */
    public function canHandle(OpInterface $op): bool
    {
        $isDeleteOperation = $op->getType()->getId() === OpTypeDelete::ID;
        $isAnswerEntity = $op->getEntityType() === 'answer';

        return $isAnswerEntity && $isDeleteOperation;
    }

    /**
     * Deletes an answer from the question.
     * This is the expected structure:
     * "patch": [
     *          {
     *              "entity": "answer",
     *              "op": "delete",
     *              "id": "12345",
     *         }
     *  ]
     *
     * @param OpInterface $op
     * @return void
     * @throws PermissionDeniedException
     * @throws OpHandlerException
     */
    public function handle(OpInterface $op)
    {
        $this->questionAggregateService->deleteAnswer(
            $this->getSurveyIdFromContext($op),
            $op->getEntityId()
        );
    }

    /**
     * Checks if patch is valid for this operation.
     * @param OpInterface $op
     * @return array
     */
    public function validateOperation(OpInterface $op): array
    {
        $validationData = $this->validateEntityId($op, []);
        return $this->getValidationReturn(
            gt('Could not delete answer option'),
            $validationData,
            $op
        );
    }
}
