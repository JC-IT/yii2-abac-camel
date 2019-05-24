<?php

namespace JCIT\abac\connectors\yii2;

/**
 * Trait AuthorizableTrait
 * @package JCIT\abac\connectors\yii2
 */
trait AuthorizableTrait
{
    /**
     * @return string
     */
    public function getAuthName(): string
    {
        return self::class;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        $pk = $this->getPrimaryKey();
        return (is_array($pk) ? implode('|', $pk) : $pk) ?? "";
    }

    /**
     * @param bool $asArray
     * @return mixed
     */
    abstract public function getPrimaryKey($asArray = false);
}
