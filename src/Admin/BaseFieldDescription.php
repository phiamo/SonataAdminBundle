<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Admin;

use Doctrine\Inflector\InflectorFactory;
use Sonata\AdminBundle\Exception\NoValueException;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * A FieldDescription hold the information about a field. A typical
 * admin instance contains different collections of fields.
 *
 * - form: used by the form
 * - list: used by the list
 * - filter: used by the list filter
 *
 * Some options are global across the different contexts, other are
 * context specifics.
 *
 * Global options :
 *   - type (m): define the field type (use to tweak the form or the list)
 *   - template (o) : the template used to render the field
 *   - name (o) : the name used (label in the form, title in the list)
 *   - link_parameters (o) : add link parameter to the related Admin class when
 *                           the Admin.generateUrl is called
 *   - code : the method name to retrieve the related value
 *   - associated_property : property path to retrieve the "string" representation
 *                           of the collection element.
 *
 * Form Field options :
 *   - field_type (o): the widget class to use to render the field
 *   - field_options (o): the options to give to the widget
 *   - edit (o) : list|inline|standard (only used for associated admin)
 *      - list : open a popup where the user can search, filter and click on one field
 *               to select one item
 *      - inline : the associated form admin is embedded into the current form
 *      - standard : the associated admin is created through a popup
 *
 * List Field options :
 *   - identifier (o): if set to true a link appear on to edit the element
 *
 * Filter Field options :
 *   - options (o): options given to the Filter object
 *   - field_type (o): the widget class to use to render the field
 *   - field_options (o): the options to give to the widget
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
abstract class BaseFieldDescription implements FieldDescriptionInterface
{
    /**
     * @var string the field name
     */
    protected $name;

    /**
     * @var string|null the type
     */
    protected $type;

    /**
     * @var string|int the original mapping type
     */
    protected $mappingType;

    /**
     * @var string|null the field name (of the form)
     */
    protected $fieldName;

    /**
     * @var array<string, mixed> association mapping
     */
    protected $associationMapping = [];

    /**
     * @var array<string, mixed> field information
     */
    protected $fieldMapping = [];

    /**
     * @var array<string, mixed> parent mapping association
     */
    protected $parentAssociationMappings = [];

    /**
     * @var string|null the template name
     */
    protected $template;

    /**
     * @var array<string, mixed> the option collection
     */
    protected $options = [];

    /**
     * @var AdminInterface|null the parent Admin instance
     */
    protected $parent;

    /**
     * @var AdminInterface|null the related admin instance
     */
    protected $admin;

    /**
     * @var AdminInterface|null the associated admin class if the object is associated to another entity
     */
    protected $associationAdmin;

    /**
     * @var string[][]
     *
     * @phpstan-var array<string, array{method: 'getter'|'call'|'var', getter?: string}>
     */
    private static $fieldGetters = [];

    public function __construct(
        string $name,
        array $options = [],
        array $fieldMapping = [],
        array $associationMapping = [],
        array $parentAssociationMappings = [],
        ?string $fieldName = null
    ) {
        $this->setName($name);

        if (null === $fieldName) {
            $fieldName = $name;
        }

        $this->fieldName = $fieldName;

        $this->setOptions($options);

        if ([] !== $fieldMapping) {
            $this->setFieldMapping($fieldMapping);
        }

        if ([] !== $associationMapping) {
            $this->setAssociationMapping($associationMapping);
        }

        if ([] !== $parentAssociationMappings) {
            $this->setParentAssociationMappings($parentAssociationMappings);
        }
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOption(string $name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    public function setOptions(array $options): void
    {
        // set the type if provided
        if (isset($options['type'])) {
            $this->setType($options['type']);
            unset($options['type']);
        }

        // remove property value
        if (isset($options['template'])) {
            $this->setTemplate($options['template']);
            unset($options['template']);
        }

        // set default placeholder
        if (!isset($options['placeholder'])) {
            $options['placeholder'] = 'short_object_description_placeholder';
        }

        if (!isset($options['link_parameters'])) {
            $options['link_parameters'] = [];
        }

        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setParent(AdminInterface $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): AdminInterface
    {
        if (null === $this->parent) {
            throw new \LogicException(sprintf('%s has no parent.', static::class));
        }

        return $this->parent;
    }

    public function hasParent(): bool
    {
        return null !== $this->parent;
    }

    public function getAssociationMapping(): array
    {
        return $this->associationMapping;
    }

    public function getFieldMapping(): array
    {
        return $this->fieldMapping;
    }

    public function getParentAssociationMappings(): array
    {
        return $this->parentAssociationMappings;
    }

    public function setAssociationAdmin(AdminInterface $associationAdmin): void
    {
        $this->associationAdmin = $associationAdmin;
        $this->associationAdmin->setParentFieldDescription($this);
    }

    public function getAssociationAdmin(): AdminInterface
    {
        if (null === $this->associationAdmin) {
            throw new \LogicException(sprintf('%s has no association admin.', static::class));
        }

        return $this->associationAdmin;
    }

    public function hasAssociationAdmin(): bool
    {
        return null !== $this->associationAdmin;
    }

    /**
     * NEXT_MAJOR: Change the visibility to protected.
     *
     * @throws NoValueException
     *
     * @return mixed
     */
    public function getFieldValue(?object $object, ?string $fieldName)
    {
        if ($this->isVirtual() || null === $object) {
            return null;
        }

        $dotPos = strpos($fieldName, '.');
        if ($dotPos > 0) {
            $child = $this->getFieldValue($object, substr($fieldName, 0, $dotPos));
            if (null !== $child && !\is_object($child)) {
                throw new NoValueException(sprintf(
                    <<<'EXCEPTION'
                    Unexpected value when accessing to the property "%s" on the class "%s" for the field "%s".
                    Expected object|null, got %s.
                    EXCEPTION,
                    $fieldName,
                    \get_class($object),
                    $this->getName(),
                    \gettype($child)
                ));
            }

            return $this->getFieldValue($child, substr($fieldName, $dotPos + 1));
        }

        // prefer method name given in the code option
        if ($this->getOption('code')) {
            $getter = $this->getOption('code');

            if (!method_exists($object, $getter)) {
                @trigger_error(
                    'Passing a non-existing method in the "code" option is deprecated'
                    .' since sonata-project/admin-bundle 3.x and will throw an exception in 4.0.',
                    \E_USER_DEPRECATED
                );

            // NEXT_MAJOR: Remove the deprecation and uncomment the next line.
//                throw new \LogicException('The method "%s"() does not exist.', $getter);
            } elseif (!\is_callable([$object, $getter])) {
                @trigger_error(
                    'Passing a non-callable method in the "code" option is deprecated'
                    .' since sonata-project/admin-bundle 3.x and will throw an exception in 4.0.',
                    \E_USER_DEPRECATED
                );

            // NEXT_MAJOR: Remove the deprecation and uncomment the next line.
//                throw new \LogicException('The method "%s"() does not have public access.', $getter);
            } else {
                if ($this->getOption('parameters')) {
                    @trigger_error(
                        'The option "parameters" is deprecated since sonata-project/admin-bundle 3.x and will be removed in 4.0.',
                        \E_USER_DEPRECATED
                    );

                    return $object->{$getter}(...$this->getOption('parameters'));
                }

                return $object->{$getter}();
            }
        }

        // NEXT_MAJOR: Remove the condition code and the else part
        if (!$this->getOption('parameters')) {
            $propertyAccesor = PropertyAccess::createPropertyAccessorBuilder()
                ->enableMagicCall()
                ->getPropertyAccessor();

            try {
                return $propertyAccesor->getValue($object, $fieldName);
            } catch (ExceptionInterface $exception) {
                throw new NoValueException(
                    sprintf('Cannot access property "%s" in class "%s".', $this->getName(), \get_class($object)),
                    $exception->getCode(),
                    $exception
                );
            }
        }

        @trigger_error(
            'The option "parameters" is deprecated since sonata-project/admin-bundle 3.x and will be removed in 4.0.',
            \E_USER_DEPRECATED
        );

        $getters = [];
        $parameters = $this->getOption('parameters');

        if (\is_string($fieldName) && '' !== $fieldName) {
            if ($this->hasCachedFieldGetter($object, $fieldName)) {
                return $this->callCachedGetter($object, $fieldName, $parameters);
            }

            $camelizedFieldName = InflectorFactory::create()->build()->classify($fieldName);

            $getters[] = lcfirst($camelizedFieldName);
            $getters[] = sprintf('get%s', $camelizedFieldName);
            $getters[] = sprintf('is%s', $camelizedFieldName);
            $getters[] = sprintf('has%s', $camelizedFieldName);
        }

        foreach ($getters as $getter) {
            if (method_exists($object, $getter) && \is_callable([$object, $getter])) {
                $this->cacheFieldGetter($object, $fieldName, 'getter', $getter);

                return $object->$getter(...$parameters);
            }
        }

        if (method_exists($object, '__call')) {
            $this->cacheFieldGetter($object, $fieldName, 'call');

            return $object->$fieldName(...$parameters);
        }

        if (isset($object->{$fieldName})) {
            $this->cacheFieldGetter($object, $fieldName, 'var');

            return $object->{$fieldName};
        }

        throw new NoValueException(sprintf(
            'Neither the property "%s" nor one of the methods "%s()" exist and have public access in class "%s".',
            $this->getName(),
            implode('()", "', $getters),
            \get_class($object)
        ));
    }

    public function setAdmin(AdminInterface $admin): void
    {
        $this->admin = $admin;
    }

    public function getAdmin(): AdminInterface
    {
        if (null === $this->admin) {
            throw new \LogicException(sprintf('%s has no admin.', static::class));
        }

        return $this->admin;
    }

    public function hasAdmin(): bool
    {
        return null !== $this->admin;
    }

    public function mergeOption(string $name, array $options = []): void
    {
        if (!isset($this->options[$name])) {
            $this->options[$name] = [];
        }

        if (!\is_array($this->options[$name])) {
            throw new \RuntimeException(sprintf('The key `%s` does not point to an array value', $name));
        }

        $this->options[$name] = array_merge($this->options[$name], $options);
    }

    public function mergeOptions(array $options = []): void
    {
        $this->setOptions(array_merge_recursive($this->options, $options));
    }

    public function getMappingType()
    {
        return $this->mappingType;
    }

    /**
     * @return string|false|null
     */
    public function getLabel()
    {
        return $this->getOption('label');
    }

    public function isSortable(): bool
    {
        return false !== $this->getOption('sortable', false);
    }

    public function getSortFieldMapping(): array
    {
        return $this->getOption('sort_field_mapping');
    }

    public function getSortParentAssociationMapping(): array
    {
        return $this->getOption('sort_parent_association_mappings');
    }

    public function getTranslationDomain(): string
    {
        return $this->getOption('translation_domain') ?: $this->getAdmin()->getTranslationDomain();
    }

    public function isVirtual(): bool
    {
        return false !== $this->getOption('virtual_field', false);
    }

    /**
     * @param array<string, mixed> $fieldMapping
     */
    abstract protected function setFieldMapping(array $fieldMapping): void;

    /**
     * @param array<string, mixed> $associationMapping
     */
    abstract protected function setAssociationMapping(array $associationMapping): void;

    /**
     *  @param array<array<string, mixed>> $parentAssociationMappings
     */
    abstract protected function setParentAssociationMappings(array $parentAssociationMappings): void;

    private function getFieldGetterKey(object $object, ?string $fieldName): ?string
    {
        if (!\is_string($fieldName)) {
            return null;
        }

        $components = [\get_class($object), $fieldName];

        $code = $this->getOption('code');
        if (\is_string($code) && '' !== $code) {
            $components[] = $code;
        }

        return implode('-', $components);
    }

    private function hasCachedFieldGetter(object $object, string $fieldName): bool
    {
        return isset(
            self::$fieldGetters[$this->getFieldGetterKey($object, $fieldName)]
        );
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return mixed
     */
    private function callCachedGetter(object $object, string $fieldName, array $parameters = [])
    {
        $getterKey = $this->getFieldGetterKey($object, $fieldName);

        if ('getter' === self::$fieldGetters[$getterKey]['method']) {
            return $object->{self::$fieldGetters[$getterKey]['getter']}(...$parameters);
        }

        if ('call' === self::$fieldGetters[$getterKey]['method']) {
            return $object->__call($fieldName, $parameters);
        }

        return $object->{$fieldName};
    }

    /**
     * @phpstan-param 'call'|'getter'|'var' $method
     */
    private function cacheFieldGetter(object $object, ?string $fieldName, string $method, ?string $getter = null): void
    {
        $getterKey = $this->getFieldGetterKey($object, $fieldName);
        if (null !== $getterKey) {
            self::$fieldGetters[$getterKey] = [
                'method' => $method,
            ];
            if (null !== $getter) {
                self::$fieldGetters[$getterKey]['getter'] = $getter;
            }
        }
    }
}
