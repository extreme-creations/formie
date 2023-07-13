<?php
namespace verbb\formie\elements\db;

use craft\elements\User;
use verbb\formie\Formie;
use verbb\formie\elements\Form;
use verbb\formie\models\Status;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class SubmissionQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    public $id;
    public $siteId = '*';
    public $formId;
    public $statusId;
    public $userId;
    public $isIncomplete = false;
    public $isSpam = false;

    protected $defaultOrderBy = ['elements.dateCreated' => SORT_DESC];


    // Public Methods
    // =========================================================================

    /**
     * Sets the [[formId]] property.
     *
     * @param Form|string|null $value The property value
     * @return static self reference
     */
    public function form($value)
    {
        if ($value instanceof Form) {
            $this->formId = $value->id;
        } else if ($value !== null) {
            $this->formId = (new Query())
                ->select(['forms.id'])
                ->from(['{{%formie_forms}} forms'])
                ->where(Db::parseParam('handle', $value))
                ->leftJoin(['{{%elements}} elements'], '[[forms.id]] = [[elements.id]]')
                ->andWhere(['dateDeleted' => null])
                ->scalar();
        } else {
            $this->formId = null;
        }

        return $this;
    }

    /**
     * Sets the [[formId]] property.
     *
     * @param int
     * @return static self reference
     */
    public function formId($value)
    {
        $this->formId = $value;

        return $this;
    }

    /**
     * Sets the [[statusId]] property.
     *
     * @param Status|string|null $value
     * @return static self reference
     */
    public function status($value)
    {
        if ($value instanceof Status) {
            $this->statusId = $value->id;
        } else if ($value !== null) {
            $this->statusId = (new Query())
                ->select(['id'])
                ->from(['{{%formie_statuses}}'])
                ->where(Db::parseParam('handle', $value))
                ->scalar();
        } else {
            $this->statusId = null;
        }

        return $this;
    }

    /**
     * Sets the [[statusId]] property.
     *
     * @param int
     * @return static self reference
     */
    public function statusId($value)
    {
        $this->statusId = $value;

        return $this;
    }

    /**
     * Sets the [[userId]] property.
     *
     * @param User|string|null $value
     * @return static self reference
     */
    public function user($value)
    {
        if ($value instanceof User) {
            $this->userId = $value->id;
        } else if ($value !== null) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($value);
            $this->userId = $user ? $user->id : false;
        } else {
            $this->userId = null;
        }

        return $this;
    }

    /**
     * Sets the [[userId]] property.
     *
     * @param int
     * @return static self reference
     */
    public function userId($value)
    {
        $this->userId = $value;

        return $this;
    }

    /**
     * Sets the [[isIncomplete]] property.
     *
     * @param bool|null $value
     * @return static self reference
     */
    public function isIncomplete($value)
    {
        $this->isIncomplete = $value;
        return $this;
    }

    /**
     * Sets the [[isSpam]] property.
     *
     * @param bool|null $value
     * @return static self reference
     */
    public function isSpam($value)
    {
        $this->isSpam = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function anyStatus()
    {
        parent::anyStatus();

        $this->isIncomplete = null;
        $this->isSpam = null;
        return $this;
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function beforePrepare(): bool
    {
        if ($this->formId !== null && empty($this->formId)) {
            throw new QueryAbortedException();
        }

        $this->joinElementTable('formie_submissions');

        // Figure out which content table to use
        $this->contentTable = null;

        if (!$this->formId) {
            $formIds = [];
            $subQuery = null;

            // Get the formIds for all submissions in thie query
            if ($this->id) {
                $subQuery = (new Query())
                    ->select(['formId'])
                    ->distinct()
                    ->from(['{{%formie_submissions}} submissions'])
                    ->where(Db::parseParam('id', $this->id));
            } else if ($this->uid) {
                // Note we're using the element table's UID
                $subQuery = (new Query())
                    ->select(['formId'])
                    ->distinct()
                    ->from(['{{%formie_submissions}} submissions'])
                    ->leftJoin(['{{%elements}} elements'], '[[submissions.id]] = [[elements.id]]')
                    ->where(Db::parseParam('elements.uid', $this->uid));
            }

            // We also need to do another query to ensure the forms haven't been deleted
            if ($subQuery) {
                $formIds = (new Query())
                    ->select(['formId'])
                    ->from(['{{%elements}} elements'])
                    ->where(['dateDeleted' => null])
                    ->andWhere(['not', ['formId' => null]])
                    ->leftJoin(['forms' => $subQuery], '[[forms.formId]] = [[elements.id]]')
                    ->andWhere(['dateDeleted' => null])
                    ->column();
            }

            if ($formIds) {
                $this->formId = count($formIds) === 1 ? $formIds[0] : $formIds;
            }
        }

        if ($this->formId && is_numeric($this->formId) && $form = Formie::$plugin->getForms()->getFormById($this->formId)) {
            $this->contentTable = $form->fieldContentTable;
        }

        $this->query->select([
            'formie_submissions.id',
            'formie_submissions.title',
            'formie_submissions.formId',
            'formie_submissions.statusId',
            'formie_submissions.userId',
            'formie_submissions.isIncomplete',
            'formie_submissions.isSpam',
            'formie_submissions.spamReason',
            'formie_submissions.snapshot',
            'formie_submissions.ipAddress',
        ]);

        if ($this->formId) {
            $this->subQuery->andWhere(Db::parseParam('formie_submissions.formId', $this->formId));
        }

        if ($this->statusId) {
            $this->subQuery->andWhere(Db::parseParam('formie_submissions.statusId', $this->statusId));
        }

        if ($this->userId !== null) {
            if (is_numeric($this->userId)) {
                $this->subQuery->andWhere(Db::parseParam('formie_submissions.userId', $this->userId));
            } else {
                return false;
            }
        }

        if ($this->isIncomplete !== null) {
            $this->subQuery->andWhere(Db::parseParam('formie_submissions.isIncomplete', $this->isIncomplete));
        }

        if ($this->isSpam !== null) {
            $this->subQuery->andWhere(Db::parseParam('formie_submissions.isSpam', $this->isSpam));
        }

        if ($this->title) {
            $this->subQuery->andWhere(Db::parseParam('formie_submissions.title', $this->title));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritDoc
     */
    protected function customFields(): array
    {
        // This method won't get called if $this->formId isn't set to a single int
        /** @var Form $form */
        try {
            $form = Form::find()->id($this->formId)->one();

            if ($form) {
                return $form->getFields();
            }
        } catch (\Throwable $e) {
            // This will throw an error when restoring a form - but that's okay
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    protected function statusCondition(string $status)
    {
        // Could potentially use a join in the main subquery to not have another query
        // but I figure this is only called when using `status(handle)`, and we shouldn't
        // let the 'regular' query suffer for this possible querying
        $statusId = (new Query())
            ->select(['id'])
            ->from(['{{%formie_statuses}}'])
            ->where(Db::parseParam('handle', $status))
            ->scalar();

        if ($statusId) {
            return ['formie_submissions.statusId' => $statusId];
        }

        return parent::statusCondition($status);
    }
}
