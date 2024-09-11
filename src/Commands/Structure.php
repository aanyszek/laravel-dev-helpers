<?php


namespace AAnyszek\LaravelDevHelpers\Commands;

use AAnyszek\LaravelDevHelpers\ModelRelationReflection;
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
    protected $signature = 'aanyszek:structure {table?}';

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
        $table = $this->getTableName();

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
        $schema = DB::select("describe {$table}");

        /**
         * From db
         */
        echo " * db columns \n";
        foreach ($schema as $column) {
            $type = $this->typeSearch($column->Type);
            echo " * @property {$type} {$column->Field} \n";
        }

        /**
         * From attributes
         */
        $modelAttributes = $this->getModelAttributes($table);

        echo " * attributes \n";
        foreach ($modelAttributes['attributes'] as $attribute) {
            echo " * @property mixed {$attribute}\n";
        }

        echo " * relations \n";
        foreach ($modelAttributes['relations'] as $name => $relation) {
            echo " * @property {$relation} {$name} \n";
        }

        echo " * scopes \n";
        foreach ($modelAttributes['scopes'] as $attribute) {
            echo " * @property static self {$attribute}\n";
        }
    }

    /**
     * Show resources body
     * @param $table
     */
    private function resources($table)
    {
        $className = $this->getClassNameFromTable($table);
        $schema = DB::select("describe {$table}");

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
            echo "\t'{$column->Field}' => \$model->{$column->Field},\n";
        }

        /**
         * From attributes
         */
        $modelAttributes = $this->getModelAttributes($table);

        echo "\t // attributes \n";
        foreach ($modelAttributes['attributes'] as $attribute) {
            echo "\t'{$attribute}' => \$model->{$attribute},\n";
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

        if (is_null($className)) {
            return [
                'attirbutes' => [],
                'scopes' => [],
                'relations' => [],
            ];
        }

        $ref = new \ReflectionClass($className);

        $attirbutes = [];
        $scopes = [];
        $relations = [];

        /** @var \ReflectionMethod $reflectionMethod */
        foreach ($ref->getMethods() as $reflectionMethod) {
            if (Str::startsWith($reflectionMethod->name, 'get') && Str::endsWith($reflectionMethod->name, 'Attribute')) {
                $method = Str::substr($reflectionMethod->name, 3, -9);
                if ($method) {
                    $attirbutes[] = Str::snake($method);
                }
            } elseif (Str::startsWith($reflectionMethod->name, 'scope')) {
                $method = Str::substr($reflectionMethod->name, 5);
                $scopes[] = lcfirst($method) . '()';
            } elseif (!$reflectionMethod->isStatic() && $reflectionMethod->isPublic()) {
                $modelRelation = (new ModelRelationReflection($reflectionMethod))->get();

                if ($modelRelation) {
                    $relations[$modelRelation['name']] = $modelRelation['relation'];
                }
            }
        }
        return [
            'attributes' => $attirbutes,
            'scopes' => $scopes,
            'relations' => $relations,
        ];
    }

    /**
     * Translate db type to php
     * @param $DBType
     * @return string
     */
    private function typeSearch($DBType)
    {
        $types = [
            'bigint' => 'int',
            'varchar' => 'string',
            'char' => 'string',
            'int' => 'int',
            'tinyint' => 'boolean',
            'tinyint(1)' => 'boolean',
            'tinyint(6)' => 'int',
            'smallint(6)' => 'int',
            'tinyint unsigned' => 'boolean',
            'date' => 'Carbon',
            'timestamp' => 'Carbon',
            'json' => 'array',
            'enum' => 'string',
            'set' => 'string',
            'text' => 'string',
            'mediumtext' => 'string',
            'longtext' => 'string',
        ];

        foreach ($types as $key => $value) {
            if (Str::startsWith($DBType, $key)) {
                return $value;
            }

        }
        return "--{$DBType}--";
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

    private function getTableName()
    {
        $table = $this->argument('table');

        if (!is_null($table)) {
            return $table;
        }

        $tables = DB::select('SHOW TABLES');
        $tableNames = [];
        foreach ($tables as $table) {
            foreach ($table as $key => $value)
                $tableNames[] = $value;
        }

        return $this->choice('Choose table:', $tableNames);
    }
}
