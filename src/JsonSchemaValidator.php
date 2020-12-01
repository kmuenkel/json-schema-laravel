<?php

namespace JsonSchema;

use stdClass;
use Illuminate\Support\Arr;
use Opis\JsonSchema\ISchema;
use Illuminate\Support\Fluent;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Opis\JsonSchema\{ValidationError, ValidationResult, Validator as BaseValidator};

/**
 * Class JsonSchemaValidator
 * @package Lti\Validators
 */
class JsonSchemaValidator extends BaseValidator implements Validator
{
    /**
     * @var ISchema|stdClass|string
     */
    protected $schema;

    /**
     * @var array|object
     */
    protected $data;

    /**
     * @var MessageBag
     */
    protected $messages;

    /**
     * @var array[]
     */
    protected $failedRules = [];

    /**
     * @var array
     */
    protected $validated = [];

    /**
     * @var array[]
     */
    protected $sometimes = [];

    /**
     * @var callable[]
     */
    protected $after = [];

    /**
     * JsonSchemaValidator constructor.
     * @param array|object $data
     * @param ISchema|string|stdClass $schema
     */
    public function __construct($data, $schema)
    {
        parent::__construct();

        $this->schema = $this->normalizeSchema($schema);
        $this->data = $data;
    }

    /**
     * @param $schema
     * @return mixed|string
     */
    protected function normalizeSchema($schema)
    {
        if (is_string($schema)) {
            if (Storage::exists($schema)) {
                $schema = Storage::exists($schema);
            } elseif (File::exists($schema)) {
                $schema = File::get($schema);
            }
        }

        return $schema;
    }

    protected function validateKeywords(
        &$docData,
        &$data,
        array $dataPointer,
        array $parentPointer,
        ISchema $doc,
        $schema,
        ValidationResult $bag
    ): bool {
        $isValid = parent::validateKeywords($docData, $data, $dataPointer, $parentPointer, $doc, $schema, $bag);

        if ($isValid) {
            $this->validated[implode('.', $dataPointer)] = $data;
        }

        return $isValid;
    }

    /**
     * @return array|object
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param ISchema|stdClass|string $schema
     * @return $this
     */
    public function setSchema($schema)
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getMessageBag()
    {
        !$this->messages && $this->passes();

        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    public function validate()
    {
        if (!$this->passes()) {
            throw new ValidationException($this);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function passes()
    {
        $isValid = true;
        $validations = array_merge([[$this->data, $this->schema]], $this->sometimes);

        foreach ($validations as $validation) {
            [$data, $schema] = $validation;
            $result = $this->getResult($data, $schema);
            $this->parseErrors($result);
            $isValid &= $result->isValid();
        }

        foreach ($this->after as $after) {
            $after();
        }

        return $isValid;
    }

    /**
     * @param array|object $data
     * @param ISchema|string|stdClass $schema
     * @return ValidationResult
     */
    protected function getResult($data, $schema)
    {
        $data = is_array($data) ? json_decode(json_encode($data)) : $data;

        if (is_string($schema) && filter_var($schema, FILTER_VALIDATE_URL)) {
            return $this->uriValidation($data, $schema);
        } elseif ($schema instanceof ISchema) {
            return $this->schemaValidation($data, $schema);
        } elseif (is_array($schema)) {
            $schema = json_decode(json_encode($schema));
        }

        return $this->dataValidation($data, $schema);
    }

    /**
     * @param ValidationResult $result
     * @return $this
     */
    public function parseErrors(ValidationResult $result)
    {
        $this->messages = new MessageBag;
        $errors = $result->getErrors();
        $ruleMap = [
            'required' => 'missing',
            'const' => 'expected'
        ];

        array_walk($errors, $nested = function (ValidationError $error, $parentRule = null) use (&$nested, $ruleMap) {
            $rule = $error->keyword();
            $rulePath = !is_null($parentRule) ? "$parentRule.$rule" : $rule;

            if (!($errors = $error->subErrors())) {
                $field = implode('.', (array)$error->dataPointer());
                $value = json_encode($error->data());

                if ($details = $error->keywordArgs()) {
                    $expected = json_encode($details[$ruleMap[$rule] ?? $rule] ?? $details);
                    $message = "Rule '$rulePath' expected '$expected'. Given '$value'";
                    $this->messages->add($field, $message);
                    $this->failedRules[$field][$rulePath] = $details;
                }
            }

            array_walk($errors, $nested, $rulePath);
        });

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function validated()
    {
        $this->validate();

        return $this->validated;
    }

    /**
     * @inheritDoc
     */
    public function fails()
    {
        return !$this->passes();
    }

    /**
     * @inheritDoc
     */
    public function failed()
    {
        return $this->failedRules;
    }

    /**
     * @inheritDoc
     */
    public function sometimes($attribute, $rules, callable $callback)
    {
        $payload = new Fluent($this->getData());

        if ($callback($payload)) {
            $value = Arr::get($this->getData(), $attribute);
            $this->sometimes[] = [$value, $rules];
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function after($callback)
    {
        $this->after[] = function () use ($callback) {
            return $callback($this);
        };

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function errors()
    {
        return $this->getMessageBag();
    }
}
