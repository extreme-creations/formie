<?php
namespace verbb\formie\gql\types\generators;

use verbb\formie\Formie;
use verbb\formie\base\NestedFieldInterface;
use verbb\formie\fields\formfields\Agree;
use verbb\formie\gql\interfaces\FieldInterface;
use verbb\formie\gql\interfaces\RowInterface;
use verbb\formie\gql\types\FieldType;

use Craft;
use craft\errors\GqlException;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use craft\gql\TypeManager;
use craft\helpers\Gql as GqlHelper;

use GraphQL\Type\Definition\Type;

class FieldGenerator implements GeneratorInterface
{
    // Public Methods
    // =========================================================================

    public static function generateTypes($context = null): array
    {
        $fieldClasses = Formie::$plugin->getFields()->getRegisteredFields();

        $gqlTypes = [];

        try {
            $schema = Craft::$app->getGql()->getActiveSchema();
        } catch (GqlException $e) {
            Craft::warning("Could not get the active GraphQL schema: {$e->getMessage()}", __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            return [];
        }

        foreach ($fieldClasses as $fieldClass) {
            $field = new $fieldClass;
            $typeName = $field->getGqlTypeName();
            $contentFieldGqlTypes = $field->getSettingGqlTypes();

            // Special-case for nested fields
            if ($field instanceof NestedFieldInterface) {
                $contentFieldGqlTypes = array_merge($contentFieldGqlTypes, [
                    'nestedRows' => [
                        'name' => 'nestedRows',
                        'type' => Type::listOf(RowInterface::getType()),
                        'description' => 'The field’s nested rows.',
                    ],
                    'fields' => [
                        'name' => 'fields',
                        'type' => Type::listOf(FieldInterface::getType()),
                        'description' => 'The field’s nested fields.',
                    ],
                ]);
            }

            if (!$field->includeInGqlSchema($schema)) {
                continue;
            }

            $fieldFields = TypeManager::prepareFieldDefinitions(array_merge(FieldInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);

            $gqlTypes[$typeName] = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new FieldType([
                'name' => $typeName,
                'fields' => function() use ($fieldFields) {
                    return $fieldFields;
                },
            ]));
        }

        return $gqlTypes;
    }
}
