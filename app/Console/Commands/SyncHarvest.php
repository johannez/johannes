<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DateTime;
use DateInterval;
use BestIt\Harvest\Client as HarvestClient;
use BestIt\Harvest\Models\Timesheet\DayEntry;

class SyncHarvest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harvest:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync your Harvest account with your client ones.';

    protected $harvest;
    protected $account;
    protected $clients;
    protected $hours_total = 0.0;
    protected $days = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

//        $this->harvest = $connection;
        $this->account = config('harvestsync.account');
        $this->clients = config('harvestsync.clients');

        // Set proper credentials for the harvest connection.
        // TODO: Switch this to OAuth.
        $this->harvest = new HarvestClient($this->account['url'], $this->account['user'], decrypt($this->account['password']));
//        $this->harvest->setAuth($this->account['user'], decrypt($this->account['password']));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Sync client Harvest account into yours:');

        // Choose the client.
        $client_names = array_keys($this->clients);

        $client_name = $this->choice('Which client?', $client_names, 0);
        $client = $this->clients[$client_name];

        // Choose the month.
        $month_default = date('Y-m');
        $year_month = $this->ask('Which month?', $month_default);
        $this->setMonth($year_month);


        // Collect all entries from the clients.
        $this->line('Collecting all entries for ' . $client['name'] . '...');
        $entries = $this->getClientEntries($client);

        $this->line("\n\n---");

        $num_entries = count($entries);
        $this->info('Number of entries: ' . $num_entries);
        $this->info('Hours total: ' . $this->hours_total);



//  TODO: Add the created entry info to a database log table.
//        $test_entry = new DayEntry();
//        $test_entry->projectId = '8583026';
//        $test_entry->taskId = '6811596';
//        $test_entry->notes = 'TEST TEST TEST';
//        $test_entry->hours = '5.25';
//        $test_entry->spentAt = '2017-09-23';
//
//
//        print_r($test_entry);
//        $created_entry = $this->harvest->timesheet()->create($test_entry);
//        print_r($created_entry);


        if ($this->confirm('Do you want to sync these entries into your Harvest account?')) {
            $this->line('Syncing all entries into your account...');
            
            $bar = $this->output->createProgressBar(count($entries));

            foreach ($entries as $entry) {
                $created_entry = $this->harvest->timesheet()->create($entry);

//                sleep(1);
                $bar->advance();
            }

            $bar->finish();
            $this->line("\n\n");
            $this->info('done.');
        }
    }

    protected function setMonth($year_month) {
        $this->days = [];

        // Prepare the month data.
        $start = new DateTime($year_month . '-01');
        $end = clone $start;
        $end->add(new DateInterval("P1M"));

        while ($start->getTimestamp() < $end->getTimestamp()) {
            $this->days[] = $start->format('Y-m-d');
            $start->add(new DateInterval("P1D"));
        }
    }


    protected function getClientEntries($client) {
        $entries = [];
        $project_names = [];

        // Get all projects from the main account for this client.
        $projects = $this->harvest->projects()->findByClientId($client['id']);


        foreach ($projects as $p) {
            $project_names[$p->id] = $p->name;
        }


        // Create new connection to Harvest for this client.
        $clientHarvest = new \BestIt\Harvest\Client($client['url'], $client['user'], decrypt($client['password']));

        $bar = $this->output->createProgressBar(count($this->days));

        foreach ($this->days as $day_month) {
            $day = new DateTime($day_month);
            $hours_day = 0.0;

            $timesheets = $clientHarvest->timesheet()->all(true, $day);

            if (!empty($timesheets->dayEntries)) {

                foreach ($timesheets->dayEntries as $de) {

                    // Check, if there is a project in the main account for this client's client.
                    if ($project_id = array_search($de->client, $project_names)) {

                        $day_entry = new DayEntry();
                        $day_entry->projectId = $project_id;
                        $day_entry->taskId = $this->account['task_id'];
                        $day_entry->notes = '[Client: ' . $de->client . ', Task: ' . $de->task . '] ' . $de->notes;
                        $day_entry->hours = $de->hours;
                        $day_entry->spentAt = $day_month;

                        $entries[] = $day_entry;
                        $this->hours_total += $de->hours;
                        $hours_day += $de->hours;

                    } else {
                        // create new project
                        // TODO
                        $this->error('Missing Project: ' . $de->client);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();

        return $entries;
    }
}
