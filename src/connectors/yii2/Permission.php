<?php

namespace JCIT\abac\connectors\yii2;

/**
 * Class Permission
 * @package common\models\activeRecord
 *
 * @property int $id [int(11)]
 * @property string $sourceName [varchar(100)]
 * @property string $sourceId [varchar(100)]
 * @property string $targetName [varchar(100)]
 * @property string $targetId [varchar(100)]
 * @property string $permission [varchar(100)]
 */
class Permission extends ActiveRecord
{
    use Authorizable;

    const PERMISSION_ADMINISTER = 'admin';
    const PERMISSION_CREATE = 'create';
    const PERMISSION_DELETE = 'delete';
    const PERMISSION_LIST = 'list';
    const PERMISSION_VIEW = 'view';
    const PERMISSION_WRITE = 'write';

    // Cache for the results for the anyAllowed lookup.
    private static $anyAllowedCache = [];

    private static $anySourceAllowedCache = [];

    // Cache for the results for the isAllowed loookup.
    private static $cache = [];

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // Clear all caches.
        self::$anyAllowedCache = [];
        self::$anySourceAllowedCache = [];
        self::$cache = [];
    }

    /**
     * Checks if a $source is allowed $permission on any $targetClass instance.
     * @param AuthorizableInterface $source
     * @param $targetName
     * @param string $permission
     * @return bool
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public static function anyAllowed(AuthorizableInterface $source, $targetName, $permission): bool
    {
        return self::anyAllowedById($source->getAuthName(), $source->getId(), $targetName, $permission);
    }

    /**
     * @param string $sourceName
     * @param string $sourceId
     * @param string $targetName
     * @param string $permission
     * @return bool
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public static function anyAllowedById(string $sourceName, string $sourceId, string $targetName, string $permission): bool
    {
        $key  = implode('|', [$sourceName, $sourceId, $targetName, $permission]);
        if (!isset(self::$anyAllowedCache[$key])){
            $query = self::find();
            $query->andWhere(['sourceName' => $sourceName, 'sourceId' => $sourceId]);
            $query->andWhere(['targetName' => $targetName, 'permission' => $permission]);
            self::$anyAllowedCache[$key] = self::getDb()->cache(function($db) use ($query) {
                return $query->exists();
            }, 120);
        }

        return self::$anyAllowedCache[$key];
    }

    /**
     * @param AuthorizableInterface $target
     * @param null $sourceName
     * @param null $permission
     * @return bool
     * @throws InvalidConfigException
     */
    public static function anySourceAllowed(AuthorizableInterface $target, $sourceName = null, $permission = null): bool
    {
        $key  = implode('|', [$target->getAuthName(), $target->getId(), $sourceName, $permission]);

        if (!isset(self::$anySourceAllowedCache[$key])) {
            $query = self::find();
            $query->andWhere([
                'targetName' => $target->getAuthName(),
                'targetId' => $target->getId(),

            ]);
            $query->andFilterWhere(['sourceName' => $sourceName]);
            $query->andFilterWhere(['permission' => $permission]);

            self::$anySourceAllowedCache[$key] = $query->exists();
        }

        return self::$anySourceAllowedCache[$key];
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'permissionLabel' => \Yii::t('app', 'Permission')
        ];
    }

    /**
     * @param $sourceName
     * @param $sourceId
     * @param $targetName
     * @param $targetId
     * @param $permission
     * @return string
     */
    private static function cacheKey($sourceName, $sourceId, $targetName, $targetId, $permission): string
    {
        return implode('|', [$sourceName, $sourceId, $targetName, $targetId, $permission]);
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public static function find(): ActiveQuery
    {
        return \Yii::createObject(PermissionQuery::class, [get_called_class()]);
    }

    /**
     * @param $sourceName
     * @param $sourceId
     * @param $targetName
     * @param $targetId
     * @param $permission
     * @return bool|null
     * @throws \Exception
     */
    private static function getCache($sourceName, $sourceId, $targetName, $targetId, $permission): ?bool
    {
        if (!isset($targetId)) {
            throw new \Exception('targetId is required');
        }
        if (\Yii::$app->authManager->debug) {
            \Yii::info("Checking from cache: $sourceName [$sourceId] --($permission)--> $targetName [$targetId]", 'abac');
        }
        $key = self::cacheKey($sourceName, $sourceId, $targetName, $targetId, $permission);
        $result = self::$cache[$key] ?? self::$cache["$sourceName|$sourceId"] ?? null;
        if (\Yii::$app->authManager->debug) {
            \Yii::info("Returning: " . ($result ? "true" : (is_null($result) ? "NULL" : "false")), 'abac');
        }
        return $result;
    }

    /**
     * @return AuthorizableInterface|null
     */
    public function getSource(): ?AuthorizableInterface
    {
        return $this->getSourceName()::findOne($this->getSourceId());
    }

    /**
     * @return string
     */
    public function getSourceId(): string
    {
        return $this->getAttribute('sourceId');
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->getAttribute('sourceName');
    }

    /**
     * @return AuthorizableInterface|null
     */
    public function getTarget(): ?AuthorizableInterface
    {
        return $this->getTargetName()::findOne($this->getTargetId());
    }

    /**
     * @return string
     */
    public function getTargetId(): string
    {
        return $this->getAttribute('targetId');
    }

    /**
     * @return string
     */
    public function getTargetName(): string
    {
        return $this->getAttribute('targetName');
    }

    /**
     * @param $sourceName
     * @param $sourceId
     * @param $targetName
     * @param $targetId
     * @param $permission
     * @return bool|null
     * @throws InvalidConfigException
     */
    public static function isAllowedById($sourceName, $sourceId, $targetName, $targetId, $permission)
    {
        self::loadCache($sourceName, $sourceId);

        if (null === ($result = self::getCache($sourceName, $sourceId, $targetName, $targetId, $permission))) {
            $query = self::find()->where([
                'sourceName' => $sourceName,
                'sourceId' => $sourceId,
                'targetName' => $targetName,
                'targetId' => $targetId,
                'permission' => $permission
            ]);

            $result = $query->exists();
            self::setCache($sourceName, $sourceId, $targetName, $targetId, $permission, $result);
        }

        return $result;
    }

    /**
     * @param $sourceName
     * @param $sourceId
     * @throws InvalidConfigException
     */
    private static function loadCache($sourceName, $sourceId)
    {
        if (!isset(self::$cache["$sourceName|$sourceId"])) {
            foreach (self::find()->where([
                'sourceName' => $sourceName,
                'sourceId' => $sourceId
            ])->each() as $grant) {
                self::setCache($sourceName, $sourceId, $grant->targetName, $grant->targetId, $grant->permission,
                    true);
            };
            self::$cache["$sourceName|$sourceId"] = false;
        }
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['sourceName', 'sourceId', 'targetName', 'targetId', 'permission'], 'required'],
            [['sourceName', 'sourceId', 'targetName', 'targetId', 'permission'], 'unique', 'targetAttribute' => ['sourceName', 'sourceId', 'targetName', 'targetId', 'permission']],
            [['permission'], StringValidator::class, 'min' => 1]
        ];
    }

    /**
     * @param string $sourceName
     * @param string $sourceId
     * @param string $targetName
     * @param string $targetId
     * @param string $permission
     * @param bool $value
     */
    private static function setCache(
        string $sourceName,
        string $sourceId,
        string $targetName,
        string $targetId,
        string $permission,
        bool $value
    ) {
        self::$cache[self::cacheKey($sourceName, $sourceId, $targetName, $targetId, $permission)] = $value;
    }
}
