<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Rules\LocationNameRule;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\File;

class CustomersMigrationFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers-migration-from-csv {migration} {errors} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate customers from csv file to database.';

    protected Customer $customer;

    protected array $errors = [];

    protected string $migrationFilename = '';
    protected string $errorsFilename = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Customer $customer)
    {
        parent::__construct();
        $this->customer = $customer;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->migrationFilename = str_replace('migration=', '', $this->argument('migration'));
        $this->errorsFilename = str_replace('errors=', '', $this->argument('errors'));
        $debug = $this->option('debug');

        // Валидацию атрибутов я решил не делать, это было бы перебор для тестового задания

        $total = 0;
        $countCreated = 0;
        $countValidationErrors = 0;
        $filename = base_path($this->migrationFilename);
        $delimiter = ',';

        /**
         * Невалидные, не значит обязательные, в моем понимании не валидные, те что не соответствуют правилам,
         * в задаче не указано, что они должны быть обязательно указаны. Но id должно быть обязательно, чтобы проверить
         * наличие в базе данных записей.
         **/

        // https://www.iban.com/country-codes

        $validationRules = [
            'id'    => 'required|integer',
            'email' => 'nullable|email:rfc,dns',
            'age'   => 'nullable|integer|min:18|max:99',
            'location'   => ['nullable', new LocationNameRule],
        ];

        $header = NULL;
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, null, $delimiter)) !== FALSE) {
                if (!$header) {
                    $header = $row;
                } else {
                    $total++;
                    $data = array_combine($header, $row);

                    $data['id'] = preg_replace( '/[^0-9]/', '', $data['id'] );
                    $data['age'] = preg_replace( '/[^0-9]/', '', $data['age'] );

                    foreach ($header as $headerKey) {
                        $data[$headerKey] = trim($data[$headerKey]);
                        if ($data[$headerKey] === '') {
                            $data[$headerKey] = null;
                        }
                    }

                    $data['id'] = $data['id'] !== null ? (int)$data['id'] : $data['id'];
                    $data['age'] = $data['age'] !== null ? (int)$data['age'] : $data['age'];

                    $validator = \Illuminate\Support\Facades\Validator::make($data, $validationRules);

                    $errors = $validator->errors();

                    $errorKeys = array_keys($errors->messages());

                    // По идее я тут мог вывести сообщения в excel, но вроде сказано только названия полей
                    if (count($errorKeys) > 0) {
                        $countValidationErrors++;
                        $this->addError(implode($delimiter, $row), implode(',', $errorKeys));
                    }

                    if(count($errorKeys) === 0 || count($errorKeys) === 1 && in_array('location', $errorKeys)){

                        $id = $data['id'];

                        if (!Customer::whereId($id)->exists()) {
                            $name_ = $data['name'] !== null ? explode(' ', $data['name']) : null;

                            $name = $name_ !== null ? $name_[0] : null;
                            $surname = $name_ !== null ? $name_[1] : null;
                            $email = $data['email'] ?? null;
                            $age = $data['age'] ?? null;
                            $location = null;
                            $countryCode = null;

                            if ($data['location'] !== null) {
                                // Невалидные, не значит обязательные, либо стоит уточнять
                                try {
                                    $ISO3166 = (new \League\ISO3166\ISO3166)->name($data['location']);
                                    $location = $ISO3166['name'];
                                    $countryCode = $ISO3166['alpha3'];
                                } catch (\OutOfBoundsException $exception) {
                                    $location = 'Unknown';
                                }
                            }

                            Customer::create([
                                'id'           => $id,
                                'name'         => $name,
                                'surname'      => $surname,
                                'email'        => $email,
                                'age'          => $age,
                                'location'     => $location,
                                'country_code' => $countryCode,
                            ]);
                            $countCreated++;
                        }

                    }

                    if($debug){
                        echo "Создано записей $countCreated из $total строк, ошибок валидации $countValidationErrors\n";
                    }
                }
            }
            fclose($handle);
        }

        $this->printErrors();

        return 0;
    }

    protected function addError(string $row, string $error)
    {
        $this->errors[] = [
            'row'   => $row,
            'error' => $error
        ];
    }

    protected function printErrors()
    {

        $data = $this->errors;
        /**
         * WARNING!!! - See documentation https://www.tinybutstrong.com/opentbs.php
         */

        // Include classes
        include_once(base_path('app/Utilities/tbs_class.php')); // Load the TinyButStrong template engine
        include_once(base_path('app/Utilities/tbs_plugin_opentbs.php')); // Load the OpenTBS plugin

        // Initialize the TBS instance
        $TBS = new \clsTinyButStrong; // new instance of TBS
        $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load the OpenTBS plugin

        // -----------------
        // Load the template
        // -----------------

        $template = base_path('resources/xslx/migration-errors/stub.xlsx');
        $result = base_path($this->errorsFilename);

        $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8); // Also merge some [onload] automatic fields (depends of the type of document).

        $TBS->PlugIn(OPENTBS_SELECT_SHEET, "Migration errors");

        $TBS->MergeBlock('a', $data);

// -----------------
// Output the result
// -----------------

// Define the name of the output file
        $output_file_name = $result;
        $TBS->Show(OPENTBS_FILE, $output_file_name); // Also merges all [onshow] automatic fields.
        // Be sure that no more output is done, otherwise the download file is corrupted with extra data.
        exit();
    }
}
