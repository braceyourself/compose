<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Braceyourself\Compose\Commands\ComposeUpCommand;
use Braceyourself\Compose\Commands\ComposePublishCommand;

class ComposePublishCommandTests extends TestCase
{
    #[Test]
    public function get_available_networks_will_return_the_available_networks_on_the_localhost()
    {
        $command = new ComposePublishCommand();


        $output = $command->getAvailableNetworks();

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
