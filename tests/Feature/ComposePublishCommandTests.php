<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Braceyourself\Compose\Commands\ComposeUpCommand;
use Braceyourself\Compose\Concerns\InteractsWithEnvFile;
use Braceyourself\Compose\Commands\ComposePublishCommand;

class ComposePublishCommandTests extends TestCase
{
    use InteractsWithEnvFile;

    #[Test]
    public function env_variables_will_be_updated_even_if_they_re_commented_out()
    {
        $this->setEnv('DB_CONNECTION', 'mysql');
        $this->setEnv('DB_PORT', 'port-test');
        $this->setEnv('DB_USERNAME', 'username-test');
        $this->setEnv('DB_PASSWORD', 'pass-test');

        $this->assertSame(config('database.default'), 'mysql');
        $this->assertSame(config('database.connections.mysql-test.port'), 'port-test');
        $this->assertSame(config('database.connections.mysql-test.username'), 'username-test');
        $this->assertSame(config('database.connections.mysql-test.password'), 'pass-test');

        dd(config('database.connections.mysql'));
    }

    /** setting the COMPOSE_PHP_IMAGE updates the compose config */
    #[Test]
    public function setting_the_compose_php_image_updates_the_compose_config()
    {
        $this->setEnv('COMPOSE_PHP_IMAGE', 'test-image');
        $this->assertSame(config('compose.services.php.image'), 'test-image');
    }

    #[Test]
    public function setting_value_of_commented_env_variable_will_remove_the_comment_symbol()
    {
        $env_original = file_get_contents(base_path('.env'));

        try {
            file_put_contents('.env', "# DB_CONNECTION=mysql\n", FILE_APPEND);

            $this->setEnv('DB_CONNECTION', 'mysql-test');
            $this->assertSame(config('database.default'), 'mysql-test');

        } catch (\Throwable $e) {
            throw $e;
        } finally {
            file_put_contents(base_path('.env'), $env_original);
        }
    }


    #[Test]
    public function running_start_will_update_the_env_file()
    {
        $this->artisan('compose:publish');

        $this->assertFileExists(base_path('.env'));
        $env = file_get_contents(base_path('.env'));

        $this->assertStringContainsString('APP_ENV=local', $env);
        $this->assertStringContainsString('APP_DEBUG=true', $env);
        $this->assertStringContainsString('APP_URL=http://localhost', $env);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);

    }

    /** hello world */
    #[Test]
    public function hello_world()
    {
        // hello world

    }


}
