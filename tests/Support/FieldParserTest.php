<?php

use LuanyCli\Support\FieldParser;
use PHPUnit\Framework\TestCase;

class FieldParserTest extends TestCase
{
    private function fields(): array
    {
        return [
            ['name' => 'title',     'type' => 'string'],
            ['name' => 'body',      'type' => 'text'],
            ['name' => 'published', 'type' => 'boolean'],
            ['name' => 'score',     'type' => 'integer'],
            ['name' => 'price',     'type' => 'decimal'],
        ];
    }

    public function test_schema_columns_per_type(): void
    {
        $result = FieldParser::toSchemaColumns([
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'published', 'type' => 'boolean'],
            ['name' => 'body', 'type' => 'text'],
            ['name' => 'price', 'type' => 'decimal'],
            ['name' => 'contact', 'type' => 'email'],
            ['name' => 'starts_at', 'type' => 'datetime'],
            ['name' => 'category_id', 'type' => 'foreign'],
        ]);

        $this->assertStringContainsString("\$table->string('title');", $result);
        $this->assertStringContainsString("\$table->boolean('published')->default(false);", $result);
        $this->assertStringContainsString("\$table->text('body');", $result);
        $this->assertStringContainsString("\$table->decimal('price', 10, 2)->default(0);", $result);
        $this->assertStringContainsString("\$table->string('contact', 150)->unique();", $result);
        $this->assertStringContainsString("\$table->dateTime('starts_at')->nullable();", $result);
        $this->assertStringContainsString("\$table->foreignId('category_id')->constrained('categories');", $result);
    }

    public function test_nullable_fields(): void
    {
        $fields = [['name' => 'bio', 'type' => 'text', 'nullable' => true], ['name' => 'age', 'type' => 'integer', 'nullable' => true]];

        $this->assertStringContainsString("\$table->text('bio')->nullable();", FieldParser::toSchemaColumns($fields));
        $this->assertStringContainsString("\$table->integer('age')->nullable();", FieldParser::toSchemaColumns($fields));

        $rules = FieldParser::toValidationRules($fields, 'people');
        $this->assertStringContainsString("'bio' => ['nullable', 'string']", $rules);
        $this->assertStringContainsString("'age' => ['nullable', 'integer']", $rules);

        $this->assertStringNotContainsString('required', FieldParser::toFormFields($fields));
    }

    public function test_schema_columns_empty(): void
    {
        $this->assertSame('', FieldParser::toSchemaColumns([]));
    }

    public function test_fillable_contains_all_fields(): void
    {
        $result = FieldParser::toFillable($this->fields());
        $this->assertStringContainsString("'title'",     $result);
        $this->assertStringContainsString("'body'",      $result);
        $this->assertStringContainsString("'published'", $result);
    }

    public function test_fillable_empty_returns_comment(): void
    {
        $this->assertStringContainsString('//', FieldParser::toFillable([]));
    }

    public function test_casts_includes_boolean_and_integer(): void
    {
        $result = FieldParser::toCasts($this->fields());
        $this->assertStringContainsString("'published' => 'bool'", $result);
        $this->assertStringContainsString("'score' => 'int'",      $result);
        $this->assertStringContainsString("'price' => 'decimal:2'",    $result);
    }

    public function test_casts_empty_when_no_castable_fields(): void
    {
        $this->assertSame('', FieldParser::toCasts([['name' => 'title', 'type' => 'string']]));
    }

    public function test_form_fields_generates_textarea_for_text(): void
    {
        $result = FieldParser::toFormFields([['name' => 'body', 'type' => 'text']]);
        $this->assertStringContainsString('<textarea', $result);
        $this->assertStringContainsString('name="body"', $result);
    }

    public function test_form_fields_generates_checkbox_for_boolean(): void
    {
        $result = FieldParser::toFormFields([['name' => 'published', 'type' => 'boolean']]);
        $this->assertStringContainsString('type="checkbox"', $result);
        $this->assertStringContainsString('name="published"', $result);
    }

    public function test_form_fields_generates_email_input(): void
    {
        $result = FieldParser::toFormFields([['name' => 'email', 'type' => 'email']]);
        $this->assertStringContainsString('type="email"', $result);
    }

    public function test_form_fields_has_required_attribute(): void
    {
        $result = FieldParser::toFormFields([['name' => 'title', 'type' => 'string']]);
        $this->assertStringContainsString('required', $result);
    }

    public function test_form_fields_has_placeholder(): void
    {
        $result = FieldParser::toFormFields([['name' => 'title', 'type' => 'string']]);
        $this->assertStringContainsString('placeholder="Enter Title"', $result);
    }

    public function test_edit_fields_has_placeholder_but_no_required(): void
    {
        $result = FieldParser::toEditFields([['name' => 'title', 'type' => 'string']], 'post');
        $this->assertStringContainsString('placeholder="Enter Title"', $result);
        $this->assertStringNotContainsString('required', $result);
    }

    public function test_show_fields_contains_property_access(): void
    {
        $result = FieldParser::toShowFields([['name' => 'title', 'type' => 'string']], 'post');
        $this->assertStringContainsString('$post->title', $result);
    }

    public function test_index_headers_contains_labels(): void
    {
        $result = FieldParser::toIndexHeaders([['name' => 'title', 'type' => 'string']]);
        $this->assertStringContainsString('<th>Title</th>', $result);
    }

    public function test_index_rows_contains_property_access(): void
    {
        $result = FieldParser::toIndexRows([['name' => 'title', 'type' => 'string']], 'post');
        $this->assertStringContainsString('$post->title', $result);
    }

    public function test_fillable_keys_for_only(): void
    {
        $result = FieldParser::toFillableKeys([
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'body',  'type' => 'text'],
        ]);
        $this->assertStringContainsString("'title'", $result);
        $this->assertStringContainsString("'body'",  $result);
    }

    public function test_fillable_keys_empty(): void
    {
        $this->assertSame('', FieldParser::toFillableKeys([]));
    }

    public function test_edit_fields_contains_prefilled_value(): void
    {
        $result = FieldParser::toEditFields([['name' => 'title', 'type' => 'string']], 'post');
        $this->assertStringContainsString('$post->title', $result);
        $this->assertStringContainsString('value=', $result);
    }

    public function test_edit_fields_textarea_has_value(): void
    {
        $result = FieldParser::toEditFields([['name' => 'body', 'type' => 'text']], 'post');
        $this->assertStringContainsString('<textarea', $result);
        $this->assertStringContainsString('$post->body', $result);
    }

    public function test_edit_fields_boolean_has_checked(): void
    {
        $result = FieldParser::toEditFields([['name' => 'published', 'type' => 'boolean']], 'post');
        $this->assertStringContainsString('checked', $result);
        $this->assertStringContainsString('$post->published', $result);
    }
}
