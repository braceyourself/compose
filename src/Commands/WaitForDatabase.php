<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;

class WaitForDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compose:wait-for-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wait for the database to be connected';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if(!config('compose.wait_for_database', false)){
            return;
        }

        $connected = false;
        do{
            try{
                \DB::connection()->getPdo();
                $connected = true;
            }catch(\Exception $e){
                $this->info('Database not connected yet. Retrying...');
                sleep(1);
            }
        }while(!$connected);
    }
}
