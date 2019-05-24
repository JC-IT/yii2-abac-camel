<?php

namespace JCIT\abac\connectors\yii2;

use SamIT\abac\interfaces\Authorizable as AuthorizableInterface;
use yii\db\ActiveQuery;

/**
 * Class PermissionQuery
 * @package common\queries
 */
class PermissionQuery extends ActiveQuery
{
    /**
     * @param AuthorizableInterface $target
     * @return PermissionQuery
     */
    public function andWhereTarget(AuthorizableInterface $target)
    {
        return $this->andWhere([
            'targetId' => $target->getId(),
            'targetName' => $target->getAuthName()
        ]);
    }

    /**
     * @param AuthorizableInterface $source
     * @return PermissionQuery
     */
    public function andWhereSource(AuthorizableInterface $source)
    {
        return $this->andWhere([
            'sourceId' => $source->getId(),
            'sourceName' => $source->getAuthName()
        ]);

    }

    /**
     * @param AuthorizableInterface|null $source
     * @return $this|PermissionQuery
     */
    public function andFilterSource(?AuthorizableInterface $source)
    {
        if (!isset($source)) {
            return $this;
        }
        return $this->andFilterWhere([
            'sourceId' => $source->getId(),
            'sourceName' => $source->getAuthName()
        ]);

    }

    /**
     * @param AuthorizableInterface|null $target
     * @return $this|PermissionQuery
     */
    public function andFilterTarget(?AuthorizableInterface $target)
    {
        if (!isset($target)) {
            return $this;
        }
        return $this->andFilterWhere([
            'targetId' => $target->getId(),
            'targetName' => $target->getAuthName()
        ]);
    }
}
