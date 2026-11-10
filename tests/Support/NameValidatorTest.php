<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\InvalidNameException;
use LuanyCli\Support\NameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NameValidatorTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function convertibleClassNames(): array
    {
        return [
            'studly'      => ['BlogPost', 'BlogPost'],
            'lowercase'   => ['post', 'Post'],
            'camel'       => ['blogPost', 'BlogPost'],
            'snake'       => ['blog_post', 'BlogPost'],
            'kebab'       => ['blog-post', 'BlogPost'],
            'with digits' => ['Oauth2Client', 'Oauth2Client'],
        ];
    }

    #[DataProvider('convertibleClassNames')]
    public function test_class_name_accepts_and_converts(string $input, string $expected): void
    {
        $this->assertSame($expected, NameValidator::className($input));
    }

    /** @return array<string, array{string}> */
    public static function invalidClassNames(): array
    {
        return [
            'empty'           => [''],
            'traversal'       => ['../../x'],
            'slash'           => ['Foo/Bar'],
            'backslash'       => ['Foo\\Bar'],
            'space'           => ['Foo Bar'],
            'leading digit'   => ['1Foo'],
            'php injection'   => ["x'];system('id');//"],
            'double sep'      => ['foo__bar'],
            'trailing sep'    => ['foo_'],
            'leading sep'     => ['_foo'],
            'dot'             => ['Foo.Bar'],
            'unicode'         => ['Café'],
        ];
    }

    #[DataProvider('invalidClassNames')]
    public function test_class_name_rejects(string $input): void
    {
        $this->expectException(InvalidNameException::class);
        NameValidator::className($input);
    }

    public function test_class_path_splits_and_validates_segments(): void
    {
        $this->assertSame(
            ['segments' => ['Admin', 'Auth'], 'class' => 'Login'],
            NameValidator::classPath('Admin/Auth/Login')
        );
        $this->assertSame(
            ['segments' => ['Auth'], 'class' => 'Login'],
            NameValidator::classPath('Auth\\Login')
        );
    }

    /** @return array<string, array{string}> */
    public static function invalidClassPaths(): array
    {
        return [
            'parent dir'     => ['../Login'],
            'nested parent'  => ['Auth/../../Login'],
            'current dir'    => ['./Login'],
            'absolute'       => ['/etc/Login'],
            'empty segment'  => ['Auth//Login'],
            'trailing slash' => ['Auth/'],
        ];
    }

    #[DataProvider('invalidClassPaths')]
    public function test_class_path_rejects(string $input): void
    {
        $this->expectException(InvalidNameException::class);
        NameValidator::classPath($input);
    }

    public function test_column_accepts_snake_case(): void
    {
        $this->assertSame('first_name', NameValidator::column('first_name'));
        $this->assertSame('_internal', NameValidator::column('_internal'));
        $this->assertSame('line2', NameValidator::column('line2'));
    }

    /** @return array<string, array{string}> */
    public static function invalidColumns(): array
    {
        return [
            'empty'         => [''],
            'uppercase'     => ['Title'],
            'leading digit' => ['2fa'],
            'quote'         => ["x'"],
            'php injection' => ["x'];system('id');//"],
            'backtick'      => ['x`; DROP TABLE users; --'],
            'space'         => ['first name'],
            'dash'          => ['first-name'],
            'reserved id'   => ['id'],
            'reserved ts'   => ['created_at'],
        ];
    }

    #[DataProvider('invalidColumns')]
    public function test_column_rejects(string $input): void
    {
        $this->expectException(InvalidNameException::class);
        NameValidator::column($input);
    }

    public function test_field_type_accepts_known_types(): void
    {
        foreach (\LuanyCli\Support\FieldParser::TYPES as $type) {
            $this->assertSame($type, NameValidator::fieldType($type));
        }
    }

    public function test_field_type_rejects_unknown_type_with_clear_message(): void
    {
        $this->expectException(InvalidNameException::class);
        $this->expectExceptionMessageMatches('/Unknown field type \[varchar\] for field \[title\].*string, text/');
        NameValidator::fieldType('varchar', 'title');
    }

    public function test_fields_rejects_duplicates(): void
    {
        $this->expectException(InvalidNameException::class);
        $this->expectExceptionMessageMatches('/Duplicate field name \[title\]/');
        NameValidator::fields([
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'title', 'type' => 'text'],
        ]);
    }

    public function test_migration_name(): void
    {
        $this->assertSame('create_users_table', NameValidator::migrationName('create_users_table'));
        $this->assertSame('add_status_to_orders', NameValidator::migrationName('add_status_to_orders'));
    }

    /** @return array<string, array{string}> */
    public static function invalidMigrationNames(): array
    {
        return [
            'traversal'     => ['../../evil'],
            'slash'         => ['foo/bar'],
            'uppercase'     => ['CreateUsersTable'],
            'leading digit' => ['1_create'],
            'injection'     => ["x\";system('id');//"],
            'space'         => ['create users'],
        ];
    }

    #[DataProvider('invalidMigrationNames')]
    public function test_migration_name_rejects(string $input): void
    {
        $this->expectException(InvalidNameException::class);
        NameValidator::migrationName($input);
    }

    public function test_view_name(): void
    {
        $this->assertSame(['pages', 'blog-post', 'index'], NameValidator::viewName('pages.blog-post.index'));
    }

    /** @return array<string, array{string}> */
    public static function invalidViewNames(): array
    {
        return [
            'traversal'     => ['..'],
            'empty segment' => ['pages..x'],
            'slash'         => ['../../etc/passwd'],
            'backslash'     => ['pages\\x'],
            'leading dash'  => ['-x'],
        ];
    }

    #[DataProvider('invalidViewNames')]
    public function test_view_name_rejects(string $input): void
    {
        $this->expectException(InvalidNameException::class);
        NameValidator::viewName($input);
    }
}
