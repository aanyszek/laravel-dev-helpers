<?php

namespace AAnyszek\LaravelDevHelpers;

class ModelRelationReflection
{
    private $allRelationTypes = [
        'hasOneThrough',
        'hasManyThrough',
        'hasMany',
        'belongsTo',
        'hasOne',
        'belongsToMany',
        'morphTo',
        'morphedByMany',
        'morphMany',
        'morphToMany',
        'morphOne'
    ];

    private $manyRelationTypes = [
        'hasManyThrough',
        'hasMany',
        'belongsToMany',
        'morphedByMany',
        'morphMany',
        'morphToMany',
    ];

    /**
     * @var \ReflectionMethod
     */
    private $reflectionMethod;

    /**
     * @param \ReflectionMethod $reflectionMethod
     */
    public function __construct($reflectionMethod)
    {
        $this->reflectionMethod = $reflectionMethod;
    }

    public function get()
    {
        $reflectionMethod = $this->reflectionMethod;
        if ($reflectionMethod->isStatic() || !$reflectionMethod->isPublic()) {
            return null;
        }

        if (in_array($reflectionMethod->getName(), $this->allRelationTypes)) {
            return null;
        }

        $type = $this->getRelationType();

        if (is_null($type)) {
            return null;
        }

        $filename = $reflectionMethod->getFileName();
        $startLine = $reflectionMethod->getStartLine() - 1;
        $endLine = $reflectionMethod->getEndLine();
        $length = $endLine - $startLine;

        $source = file($filename);
        $body = implode("", array_slice($source, $startLine, $length));
        $pattern = '/\b(?:' . implode('|', $this->allRelationTypes) . ')\s*\(\s*([A-Za-z0-9_]+)::class\s*\)/';

        // Perform the regex match
        if (preg_match($pattern, $body, $matches)) {
            return [
                'type' => $type,
                'name' => $reflectionMethod->name,
                'relation' => (in_array($type, $this->manyRelationTypes) ? 'Collection|' : '') . ($matches[1] ?? "--{$type}--") . (in_array($type, $this->manyRelationTypes) ? '[]' : ''),
            ];
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function getRelationType()
    {
        $reflectionMethod = $this->reflectionMethod;
        $pattern = '/(' . implode('|', $this->allRelationTypes) . ')/i';

        if ($reflectionMethod->getReturnType()) {
            $name = $reflectionMethod->getReturnType()->getName();

            preg_match($pattern, $name, $matches);
            if (isset($matches[1])) {
                return lcfirst($matches[1]);
            }
        }

        if ($reflectionMethod->getDocComment()) {
            preg_match($pattern, $reflectionMethod->getDocComment(), $matches);
            if (isset($matches[1])) {
                return lcfirst($matches[1]);
            }
        }

        return null;
    }
}