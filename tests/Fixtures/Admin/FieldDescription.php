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

namespace Sonata\AdminBundle\Tests\Fixtures\Admin;

use Sonata\AdminBundle\Admin\BaseFieldDescription;

class FieldDescription extends BaseFieldDescription
{
    protected function setAssociationMapping($associationMapping): void
    {
        $this->associationMapping = $associationMapping;
    }

    public function getTargetEntity(): ?string
    {
        throw new \BadMethodCallException(sprintf('Implement %s() method.', __METHOD__));
    }

    public function getTargetModel(): ?string
    {
        throw new \BadMethodCallException(sprintf('Implement %s() method.', __METHOD__));
    }

    protected function setFieldMapping($fieldMapping): void
    {
        $this->fieldMapping = $fieldMapping;
    }

    public function isIdentifier(): bool
    {
        throw new \BadMethodCallException(sprintf('Implement %s() method.', __METHOD__));
    }

    protected function setParentAssociationMappings(array $parentAssociationMappings): void
    {
        $this->parentAssociationMappings = $parentAssociationMappings;
    }

    public function getValue($object)
    {
        throw new \BadMethodCallException(sprintf('Implement %s() method.', __METHOD__));
    }
}
