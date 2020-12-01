# Laravel JSON-Schema Validator

This is simply an override class for the opis/json-schema package which leverages a Laravel interface, enabling it to be compatible with custom Request classes. To implement it, simply use the `JsonSchema\JsonSchemaValidation` trait inyour custom instance of `Illuminate\Foundation\Http\FormRequest`. Then either apply the schema filepath, storage path, URL, array, object, or string, as the output of your `rules()` method.