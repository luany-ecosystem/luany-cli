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

    public function test_migration_columns_for_string(): void
    {
        $result = FieldParser::toMigrationColumns([['name' => 'title', 'type' => 'string']]);
        $this->assertStringContainsString('`title` VARCHAR(255)', $result);
    }

    public function test_migration_columns_for_boolean(): void
    {
        $result = FieldParser::toMigrationColumns([['name' => 'published', 'type' => 'boolean']]);
        $this->assertStringContainsString('`published` TINYINT(1)', $result);
    }

    public function test_migration_columns_for_text(): void
    {
        $result = FieldParser::toMigrationColumns([['name' => 'body', 'type' => 'text']]);
        $this->assertStringContainsString('`body` TEXT', $result);
    }

    public function test_migration_columns_empty(): void
    {
        $this->assertSame('', FieldParser::toMigrationColumns([]));
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
        $this->assertStringContainsString("'price' => 'float'",    $result);
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
