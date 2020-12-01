<?php

namespace JsonSchema;

use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Validation\{Validator, Factory as ValidationFactory};

/**
 * Trait JsonSchemaValidation
 * @package JsonSchema
 */
trait JsonSchemaValidation
{
    /**
     * @param ValidationFactory $factory
     * @return Validator
     */
    public function validator(ValidationFactory $factory)
    {
        return app(JsonSchemaValidator::class, ['data' => $this->all(), 'schema' => $this->rules()]);
    }
}
