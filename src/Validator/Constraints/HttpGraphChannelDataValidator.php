<?php
/**
 * Date: 2019-01-02
 * Time: 20:34
 */

namespace Deliveryman\Validator\Constraints;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

/**
 * @Annotation
 *
 * Class HttpGraphChannelDataValidator
 * @package Deliveryman\Validator\Constraints
 */
class HttpGraphChannelDataValidator extends ConstraintValidator
{
    /**
     * @param null|BatchRequest $value
     * @param Constraint|HttpGraphChannelData $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$value || $value->getData() === null) {
            return; // allow blank
        }

        $this->validateInputType($value, $constraint);
        $this->validateRequestsIndependently($value);
        $this->validateRequestIds($value, $constraint);
    }

    /**
     * @param BatchRequest $value
     * @param Constraint|HttpGraphChannelData $constraint
     */
    protected function validateInputType($value, Constraint $constraint): void
    {
        foreach ($value->getData() as $index => $request) {
            if (!$request instanceof HttpRequest) {
                $this->context->buildViolation($constraint->messageRequestExpected, ['{{ type }}' => gettype($request)])
                    ->atPath('data[' . $index . ']')
                    ->addViolation();
            }
        }
    }

    /**
     * @param BatchRequest $value
     */
    protected function validateRequestsIndependently($value): void
    {
        if ($this->context->getViolations()->count() === 0) {
            $validator = Validation::createValidator();
            $violations = $validator->validate($value->getData(), array(
                new Valid(),
            ));

            foreach ($violations as $violation) {
                $this->context->addViolation($violation);
            }
        }
    }

    /**
     * @param BatchRequest $value
     * @param Constraint|HttpGraphChannelData $constraint
     */
    protected function validateRequestIds($value, Constraint $constraint): void
    {
        $idIndexes = [];
        foreach ($value->getData() as $index => $request) {
            if ($request instanceof HttpRequest && $request->getId()) {
                $idIndexes[$request->getId()][] = $index;
            }
        }

        foreach ($idIndexes as $id => $indexes) {
            if (isset($indexes[1])) {
                $this->context->buildViolation($constraint->messageRequestIdAmbiguous, ['{{ id }}' => $id])
                    ->atPath('data[' . $indexes[0] . '].id')
                    ->addViolation();
            }
        }

        /** @var HttpRequest $request */
        foreach ($value->getData() as $index => $request) {
            if ($request instanceof HttpRequest) {
                foreach ($request->getReq() as $reqIndex => $reqId) {
                    if (!isset($idIndexes[$reqId])) {
                        $this->context->buildViolation($constraint->messageRequestRefIdNotExist, ['{{ id }}' => $reqId])
                            ->atPath('data[' . $index . '].req[' . $reqIndex . ']')
                            ->addViolation();
                    }
                }
            }
        }
    }
}