<?php

namespace PayOS\Core;

use BackedEnum;
use DateTime;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use UnitEnum;

/**
 * ObjectSerializer Class
 */
class ObjectSerializer
{
    /**
     * Serialize an object to an array
     *
     * @param object|array|scalar|null $data The data to serialize
     * @return array|scalar|null The serialized data
     */
    public static function toArray($data)
    {
        if ($data === null) {
            return null;
        }

        if (is_scalar($data)) {
            return $data;
        }

        if ($data instanceof DateTime) {
            return $data->format(DateTime::ATOM);
        }

        if ($data instanceof BackedEnum) {
            return $data->value;
        }

        if ($data instanceof UnitEnum) {
            return $data->name;
        }

        if (is_array($data)) {
            return array_map([self::class, 'toArray'], $data);
        }

        // Must be an object at this point
        $result = [];
        $reflection = new ReflectionClass($data);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $value = $property->getValue($data);

            if ($value === null) {
                // Include null values in the result
                $result[$propertyName] = null;
            } else {
                $result[$propertyName] = self::toArray($value);
            }
        }

        return $result;
    }

    /**
     * Deserialize data to an object of the specified class
     *
     * @param mixed $data The data to deserialize (array, object, or scalar)
     * @param string $className The fully qualified class name (can include [] for arrays)
     * @return mixed The deserialized object or array of objects
     * @throws InvalidArgumentException If the class doesn't exist or data is invalid
     */
    public static function fromArray($data, $className)
    {
        if ($data === null) {
            return null;
        }

        // Handle array of objects (e.g., "ClassName[]")
        if (substr($className, -2) === '[]') {
            $itemClassName = substr($className, 0, -2);
            if (!is_array($data)) {
                throw new InvalidArgumentException("Expected array for type {$className}");
            }

            return array_map(fn ($item) => self::fromArray($item, $itemClassName), $data);
        }

        // Handle primitive types
        if (self::isPrimitiveType($className)) {
            return self::castToPrimitive($data, $className);
        }

        // Handle DateTime
        // Handle DateTime
        if ($className === DateTime::class || $className === '\DateTime') {
            if ($data instanceof DateTime) {
                return $data;
            }
            if (is_string($data) && !empty($data)) {
                return new DateTime($data);
            }

            return null;
        }

        // Handle enums
        if (self::isEnum($className)) {
            return self::deserializeEnum($data, $className);
        }

        // Handle object classes
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class {$className} does not exist");
        }

        // Convert stdClass or array to associative array
        if ($data instanceof \stdClass) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException("Expected array or object for class {$className}");
        }

        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstanceWithoutConstructor();
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $data)) {
                continue;
            }

            $value = $data[$propertyName];
            $type = $property->getType();

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $isNullable = $type->allowsNull();

                if ($value === null) {
                    if ($isNullable) {
                        $property->setValue($instance, null);
                    }

                    continue;
                }

                // Handle array types with PHPDoc annotation
                if ($typeName === 'array') {
                    $docComment = $property->getDocComment();
                    if ($docComment && preg_match('/@var\s+([^\s\[\]]+)\[\]/', $docComment, $matches)) {
                        $itemType = $matches[1];
                        // Resolve relative class names
                        $itemType = self::resolveClassName($itemType, $reflection->getNamespaceName());
                        $deserializedArray = [];
                        foreach ($value as $item) {
                            $deserializedArray[] = self::fromArray($item, $itemType);
                        }
                        $property->setValue($instance, $deserializedArray);
                    } else {
                        $property->setValue($instance, $value);
                    }
                } elseif (self::isPrimitiveType($typeName)) {
                    $property->setValue($instance, self::castToPrimitive($value, $typeName));
                } elseif ($typeName === DateTime::class) {
                    $property->setValue($instance, self::fromArray($value, DateTime::class));
                } elseif (self::isEnum($typeName)) {
                    $property->setValue($instance, self::deserializeEnum($value, $typeName));
                } elseif (class_exists($typeName)) {
                    $property->setValue($instance, self::fromArray($value, $typeName));
                } else {
                    $property->setValue($instance, $value);
                }
            } else {
                // No type hint or union type - set value as-is
                $property->setValue($instance, $value);
            }
        }

        return $instance;
    }

    /**
     * Check if a type name represents a primitive type
     *
     * @param string $typeName The type name to check
     * @return bool True if primitive type
     */
    private static function isPrimitiveType(string $typeName): bool
    {
        return in_array($typeName, [
            'int',
            'integer',
            'float',
            'double',
            'string',
            'bool',
            'boolean',
            'mixed',
        ], true);
    }

    /**
     * Cast a value to a primitive type
     *
     * @param mixed $value The value to cast
     * @param string $type The primitive type name
     * @return mixed The casted value
     */
    private static function castToPrimitive($value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'mixed':
            default:
                return $value;
        }
    }

    /**
     * Check if a class is an enum
     *
     * @param string $className The class name to check
     * @return bool True if the class is an enum
     */
    private static function isEnum(string $className): bool
    {
        if (!enum_exists($className)) {
            return false;
        }

        return true;
    }

    /**
     * Deserialize a value to an enum
     *
     * @param mixed $value The value to deserialize (can be the enum value or name)
     * @param string $enumClassName The enum class name
     * @return BackedEnum|UnitEnum The enum instance
     * @throws InvalidArgumentException If the value is invalid for the enum
     */
    private static function deserializeEnum($value, string $enumClassName)
    {
        if (!enum_exists($enumClassName)) {
            throw new InvalidArgumentException("{$enumClassName} is not a valid enum");
        }

        if ($value instanceof $enumClassName) {
            return $value;
        }

        // Try BackedEnum first (has values)
        if (is_subclass_of($enumClassName, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $enumClassName */
            try {
                return $enumClassName::from($value);
            } catch (\ValueError $e) {
                $cases = array_map(fn ($case) => $case->value, $enumClassName::cases());

                throw new InvalidArgumentException(
                    "Invalid value '{$value}' for enum {$enumClassName}. Valid values: " . implode(', ', $cases)
                );
            }
        }

        // Try UnitEnum (no values, just names)
        if (is_subclass_of($enumClassName, UnitEnum::class)) {
            /** @var class-string<UnitEnum> $enumClass */
            $enumClass = $enumClassName;
            foreach ($enumClass::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }
            $cases = array_map(fn ($case) => $case->name, $enumClass::cases());

            throw new InvalidArgumentException(
                "Invalid name '{$value}' for enum {$enumClassName}. Valid names: " . implode(', ', $cases)
            );
        }

        /* @phpstan-ignore-next-line deadCode.unreachable */
        throw new InvalidArgumentException("{$enumClassName} is not a valid enum");
    }

    /**
     * Resolve a class name relative to a namespace
     *
     * @param string $className The class name (may be relative)
     * @param string $namespace The current namespace
     * @return string The fully qualified class name
     */
    private static function resolveClassName(string $className, string $namespace): string
    {
        // Already fully qualified
        if ($className[0] === '\\') {
            return $className;
        }

        // Check if it's a built-in type
        if (self::isPrimitiveType($className) || $className === 'DateTime' || $className === 'array') {
            return $className;
        }

        // Resolve relative to current namespace
        return $namespace . '\\' . $className;
    }

    /**
     * Sanitize data for serialization
     *
     * @param mixed $data The data to serialize
     * @return array|scalar|null The sanitized data
     */
    public static function sanitizeForSerialization($data)
    {
        return self::toArray($data);
    }

    /**
     * Deserialize data
     *
     * @param mixed $data The data to deserialize
     * @param string $className The target class name
     * @return mixed The deserialized object
     */
    public static function deserialize($data, string $className)
    {
        return self::fromArray($data, $className);
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the parameter. If it's a string, pass through unchanged
     * If it's a datetime object, format it in ISO8601
     *
     * @param string|\DateTime $value the value of the parameter
     *
     * @return string the header string
     */
    public static function toString($value)
    {
        if ($value instanceof \DateTime) { // datetime in ISO8601 format
            return $value->format(\DateTime::ATOM);
        } else {
            return $value;
        }
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the header. If it's a string, pass through unchanged
     * If it's a datetime object, format it in ISO8601
     *
     * @param string $value a string which will be part of the header
     *
     * @return string the header string
     */
    public static function toHeaderValue($value)
    {
        return self::toString($value);
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the http body (form parameter). If it's a string, pass through unchanged
     * If it's a datetime object, format it in ISO8601
     *
     * @param string|\SplFileObject $value the value of the form parameter
     *
     * @return string the form string
     */
    public static function toFormValue($value)
    {
        if ($value instanceof \SplFileObject) {
            return $value->getRealPath();
        } else {
            return self::toString($value);
        }
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the path, by url-encoding.
     *
     * @param string $value a string which will be part of the path
     *
     * @return string the serialized object
     */
    public static function toPathValue($value)
    {
        return rawurlencode(self::toString($value));
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the query, by imploding comma-separated if it's an object.
     * If it's a string, pass through unchanged. It will be url-encoded
     * later.
     *
     * @param string[]|string|\DateTime $object an object to be serialized to a string
     *
     * @return string the serialized object
     */
    public static function toQueryValue($object)
    {
        if (is_array($object)) {
            return implode(',', $object);
        } else {
            return self::toString($object);
        }
    }
}
