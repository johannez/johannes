<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Johannez\Harvest\Connection;
use DateTime;
use DateInterval;

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
    public function __construct(Connection $connection)
    {
        parent::__construct();

        $this->harvest = $connection;
        $this->account = config('harvest.account');
        $this->clients = config('harvest.clients');

        // Set proper credentials for the harvest connection.
        // TODO: Switch this to OAuth.
        $this->harvest->setAuth($this->account['user'], decrypt($this->account['password']));
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
        $client_id = $this->clients[$client_name]['id'];

        // Choose the month.
        $month_default = date('Y-m');
        $year_month = $this->ask('Which month?', $month_default);
        $this->setMonth($year_month);


        // Collect all entries from the clients.
        $this->line('Collecting all entries for this client...');
        $entries = $this->getClientEntries($client_name, $client_id);

        $this->line("\n\n---");

        $num_entries = count($entries);
        $this->info('Number of entries: ' . $num_entries);
        $this->info('Hours total: ' . $this->hours_total);

//        d($entries);
//        d($hours_total);
//        d(time() - $start_time . ' seconds');


        if ($this->confirm('Do you want to sync these entries into your Harvest account?')) {
            $this->line('Syncing all entries into your account...');
            $this->harvest->setAccount($this->account['id']);

            $bar = $this->output->createProgressBar(count($entries));

            foreach ($entries as $entry) {
//                $harvest->timesheet()->create($entry);
                sleep(1);
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


    protected function getClientEntries($client_name, $client_id) {
        $entries = [];
        $project_names = [];

        // Get all projects from the main account for this client.
        $this->harvest->setAccount($this->account['id']);
        $projects = $this->harvest->project()->getAll(['client=' . $client_id]);

//        d($projects);

        foreach ($projects as $p) {
            $project_names[$p->project->id] = $p->project->name;
        }

//        d($project_names);

        // Switch to client account.
        $this->harvest->setAccount($client_name);


        $bar = $this->output->createProgressBar(count($this->days));

        foreach ($this->days as $day_month) {
            $day = new DateTime($day_month);
            $hours_day = 0.0;
            $day_entries = [];

            $doy = intval($day->format('z')) + 1;
            $year = $day->format('Y');

            $timesheet = $this->harvest->timesheet()->getByDate($doy, $year);

            if (!empty($timesheet->day_entries)) {

                foreach ($timesheet->day_entries as $de) {

                    // Check, if there is a project in the main account for this client's client.
                    if ($project_id = array_search($de->client, $project_names)) {
                        // Create a new entry.
                        $entries[] = [
                            'project_id' => $project_id,
                            'task_id' => $this->account['task_id'],
                            'notes' => '[Client: ' . $de->client . ', Task: ' . $de->task . '] ' . $de->notes,
                            'hours' => $de->hours,
                            'spent_at' => $day_month
                        ];

                        $day_entries[] = [
                            'notes' => '[Client: ' . $de->client . ', Task: ' . $de->task . '] ' . $de->notes,
                            'hours' => $de->hours
                        ];

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
