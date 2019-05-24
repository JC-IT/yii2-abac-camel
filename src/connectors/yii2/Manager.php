<?php

namespace JCIT\abac\connectors\yii2;

use SamIT\abac\connectors\yii2\Manager as SamITManager;
use yii\base\InvalidConfigException;

/**
 * Class AuthManager
 * @package JCIT\abac\connectors\yii2
 */
class Manager extends SamITManager
{
    /**
     * @inheritdoc
     */
    public function findExplicit(
        string $sourceName = null,
        string $sourceId = null,
        string $targetName = null,
        string $targetId = null,
        string $permission = null
    ): array {
        // We use array_filter to remove NULL, not empty strings.
        return Permission::find()
            ->andWhere(array_filter([
                'sourceName' => $sourceName,
                'sourceId' => $sourceId,
                'targetName' => $targetName,
                'targetId' => $targetId,
                'permission' => $permission
            ], function($e) { return $e !== null; }))
            ->all();
    }

    /**
     * @inheritdoc
     */
    protected function grantInternal(
        string $sourceName,
        string $sourceId,
        string $targetName,
        string $targetId,
        string $permission
    ): void {
        try {
            $perm = new Permission();
            $perm->sourceName = $sourceName;
            $perm->sourceId = $sourceId;
            $perm->targetName = $targetName;
            $perm->targetId = $targetId;
            $perm->permission = $permission;
            if (!$perm->save()) {
                throw new \Exception("Failed to grant permission.");
            }
        } catch (\yii\db\Exception $e) {
            throw new \Exception("Failed to grant permission.", $e);
        }
    }

    /**
     * @inheritdoc
     */
    protected function isAllowedExplicit(
        string $sourceName,
        string $sourceId,
        string $targetName,
        string $targetId,
        string $permission
    ): bool {
        return Permission::isAllowedById($sourceName, $sourceId, $targetName, $targetId, $permission);
    }

    /**
     * @inheritdoc
     */
    protected function revokeInternal(
        string $sourceName,
        string $sourceId,
        string $targetName,
        string $targetId,
        string $permission
    ): void {
        Permission::deleteAll([
            'sourceName' => $sourceName,
            'sourceId' => $sourceId,
            'targetName' => $targetName,
            'targetId' => $targetId,
            'permission' => $permission
        ]);
    }

}