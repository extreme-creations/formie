<?php
namespace verbb\formie\gql\types;

use craft\gql\base\ObjectType;

use GraphQL\Type\Definition\ResolveInfo;

class KeyValueType extends ObjectType
{
    // Public Methods
    // =========================================================================

    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        // Normalize some properties
        if (is_array($source) && array_key_exists('optgroup', $source) && $resolveInfo->fieldName === 'label') {
            $resolveInfo->fieldName = 'optgroup';
        }

        return $source[$resolveInfo->fieldName] ?? null;
    }
}
