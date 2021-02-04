<?php


namespace AAnyszek\LaravelDevHelpers\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Structure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aanyszek:structure {table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get structure for resource and model description from database table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $table = $this->argument('table');

        $this->info('Model annotations:');
        $this->modelAnnotations($table);
        $this->info('Resource body:');
        $this->resources($table);
    }

    /**
     * Show model annotations
     * @param $table
     */
    private function modelAnnotations($table)
    {
        $schema = DB::select("describe $table");

        /**
         * From db
         */
        echo " * db columns \n";
        foreach ($schema as $column) {
            $type = $this->typeSearch($column->Type);
            echo " * @property $type " . $column->Field . "\n";
        }

        /**
         * From attributes
         */
        echo " * attributes \n";
        foreach ($this->getModelAttributes($table) as $attribute) {
            echo " * @property mixed $attribute\n";
        }

        echo " * relations \n";
        echo " * scopes \n";
    }

    /**
     * Show resources body
     * @param $table
     */
    private function resources($table)
    {
        $className = $this->getClassNameFromTable($table);
        $schema    = DB::getSchemaBuilder()->getColumnListing($table);

        if ($className) {
            echo "/** @var \\$className \$model */\n";
        }
        echo "\$model = \$this->resource;\n";
        echo "return [\n";

        /**
         * From db
         */
        echo "\t // db columns \n";
        foreach ($schema as $column) {
            echo "\t'$column' => \$model->$column,\n";
        }

        /**
         * From attributes
         */
        echo "\t // attributes \n";
        foreach ($this->getModelAttributes($table) as $attribute) {
            echo "\t'$attribute' => \$model->$attribute,\n";
        }
        echo "\t // relations \n";
        echo "];\n";
    }

    /**
     * Get model attributes
     * @param $table
     * @return array
     */
    private function getModelAttributes($table)
    {
        $className = $this->getClassNameFromTable($table);
        $methods   = get_class_methods($className);
        $r         = [];
        if ($methods) {
            foreach ($methods as $methodName) {
                if (Str::startsWith($methodName, 'get') && Str::endsWith($methodName, 'Attribute')) {
                    $method = Str::substr($methodName, 3, -9);
                    if ($method) {
                        $r[] = Str::snake($method);
                    }
                }
            }
        }

        return $r;
    }

    /**
     * Translate db type to php
     * @param $DBType
     * @return string
     */
    private function typeSearch($DBType)
    {
        $types = [
            'bigint'     => 'int',
            'varchar'    => 'string',
            'char'       => 'string',
            'int'        => 'int',
            'date'       => 'Carbon',
            'timestamp'  => 'Carbon',
            'json'       => 'array',
            'enum'       => 'string',
            'set'        => 'string',
            'tinyint(1)' => 'boolean',
            'text'       => 'string',
        ];

        foreach ($types as $key => $value) {
            if (Str::startsWith($DBType, $key)) {
                return $value;
            }

        }
        return "-DBType-";
    }

    /**
     * Get model class from table name
     * @param $table
     * @return string|null
     */
    private function getClassNameFromTable($table)
    {
        $className = 'App\\Models\\' . Str::studly(Str::singular($table));

        if (class_exists($className)) {
            return $className;
        }

        $className = 'App\\Models\\' . Str::studly($table);
        if (class_exists($className)) {
            return $className;
        }

        return null;
    }

}
