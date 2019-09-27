<?php

namespace barrelstrength\sproutforms\services;

use barrelstrength\sproutforms\base\Rule;
use barrelstrength\sproutforms\base\RuleInterface;
use barrelstrength\sproutforms\base\ConditionalLogic;
use barrelstrength\sproutforms\conditionallogictypes\MissingIntegration;
use barrelstrength\sproutforms\records\ConditionalLogic as ConditionalLogicRecord;
use barrelstrength\sproutforms\SproutForms;
use craft\base\Component;
use craft\db\Query;
use craft\errors\MissingComponentException;
use craft\events\RegisterComponentTypesEvent;
use Craft;
use craft\helpers\Component as ComponentHelper;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\InvalidConfigException;


/**
 *
 * @property array                                         $integrationOptions
 * @property array                                         $allConditionalsTypes
 * @property array|\barrelstrength\sproutforms\base\Rule[] $allConditionals
 */
class Conditionals extends Component
{
    const EVENT_REGISTER_CONDITIONALS = 'registerConditionals';

    /**
     * Returns all registered Conditional Logic Types
     *
     * @return array
     */
    public function getAllConditionalsTypes(): array
    {
        $event = new RegisterComponentTypesEvent([
            'types' => []
        ]);

        $this->trigger(self::EVENT_REGISTER_CONDITIONALS, $event);

        return $event->types;
    }

    /**
     * @param $config
     *
     * @return RuleInterface
     * @throws InvalidConfigException
     */
    public function createIntegration($config): RuleInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        try {
            /** @var Rule $conditional */
            $conditional = ComponentHelper::createComponent($config, RuleInterface::class);
        } catch (MissingComponentException $e) {
            $config['errorMessage'] = $e->getMessage();
            $config['expectedType'] = $config['type'];
            unset($config['type']);

            $conditional = new MissingIntegration($config);
        }

        return $conditional;
    }

    /**
     * @return Rule[]
     */
    public function getAllConditionals(): array
    {
        $conditionalTypes = $this->getAllConditionalsTypes();

        $conditionals = [];

        foreach ($conditionalTypes as $conditionalType) {
            $conditionals[] = new $conditionalType();
        }

        return $conditionals;
    }

    /**
     * @return array
     */
    public function getIntegrationOptions(): array
    {
        $conditionals = $this->getAllConditionals();

        $options[] = [
            'label' => Craft::t('sprout-forms', 'Add Rule...'),
            'value' => ''
        ];

        foreach ($conditionals as $conditional) {
            $options[] = [
                'label' => $conditional::displayName(),
                'value' => get_class($conditional)
            ];
        }

        return $options;
    }

    /**
     * @param $formId
     * @param $type
     * @param $enabled
     *
     * @return array
     * @throws InvalidConfigException
     * @throws MissingComponentException
     */
    public function getFormConditionals($formId, $type = null, $enabled = null): array
    {
        $query = (new Query())
            ->select([
                'conditional.id',
                'conditional.formId',
                'conditional.name',
                'conditional.type',
                'conditional.behaviorAction',
                'conditional.behaviorTarget',
                'conditional.settings',
                'conditional.enabled'
            ])
            ->from(['{{%sproutforms_conditionals}} conditional'])
            ->where(['conditional.formId' => $formId]);

        if ($type !== null) {
            $query->andWhere('conditional.type = :type', [':type' => $type]);
        }

        if ($enabled !== null) {
            $query->andWhere('conditional.enabled = :enabled', [':enabled' => $enabled]);
        }

        $results = $query->all();

        $conditionals = [];

        foreach ($results as $result) {
            $conditional = ComponentHelper::createComponent($result, RuleInterface::class);
            $conditionals[] = new $result['type']($conditional);
        }

        return $conditionals;
    }

    /**
     * @param $conditionalId
     *
     * @return Rule|null
     * @throws InvalidConfigException
     * @throws MissingComponentException
     */
    public function getConditionalById($conditionalId)
    {
        $result = (new Query())
            ->select([
                'conditional.id',
                'conditional.formId',
                'conditional.name',
                'conditional.type',
                'conditional.behaviorAction',
                'conditional.behaviorTarget',
                'conditional.settings',
                'conditional.enabled'
            ])
            ->from(['{{%sproutforms_conditionals}} conditional'])
            ->where(['conditional.id' => $conditionalId])
            ->one();

        if (!$result) {
            return null;
        }

        /** @var Rule $conditional * */
        $conditional = ComponentHelper::createComponent($result, RuleInterface::class);

        return new $result['type']($conditional);
    }

    /**
     * @param Rule $conditionalLogic
     *
     * @return bool
     */
    public function saveConditional(Rule $conditionalLogic): bool
    {
        if ($conditionalLogic->id) {
            $conditionalRecord = ConditionalLogicRecord::findOne($conditionalLogic->id);
        } else {
            $conditionalRecord = new ConditionalLogicRecord();
        }

        $conditionalRecord->type = get_class($conditionalLogic);
        $conditionalRecord->formId = $conditionalLogic->formId;
        $conditionalRecord->name = $conditionalLogic->name ?? $conditionalLogic::displayName();
        $conditionalRecord->enabled = $conditionalLogic->enabled;
        $conditionalRecord->behaviorAction = $conditionalLogic->behaviorAction;
        $conditionalRecord->behaviorTarget = $conditionalLogic->behaviorTarget;

        $conditionalRecord->settings = $conditionalLogic->getSettings();

        if ($conditionalRecord->save()) {
            $conditionalLogic->id = $conditionalRecord->id;
            $conditionalLogic->name = $conditionalRecord->name;
            return true;
        }

        return false;
    }

    /**
     * @param $config
     *
     * @return RuleInterface
     * @throws InvalidConfigException
     */
    public function createConditional($config): RuleInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        try {
            /** @var ConditionalLogic $conditional */
            $conditional = ComponentHelper::createComponent($config, RuleInterface::class);
        } catch (MissingComponentException $e) {
            $config['errorMessage'] = $e->getMessage();
            $config['expectedType'] = $config['type'];
            unset($config['type']);

            $conditional = new MissingIntegration($config);
        }

        return $conditional;
    }

    /**
     * Loads the sprout modal conditional via ajax.
     *
     * @param Rule $conditional
     *
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getModalConditionalTemplate(Rule $conditional): array
    {
        $view = Craft::$app->getView();

        $html = $view->renderTemplate('sprout-forms/forms/_editConditionalModal', [
            'conditional' => $conditional,
        ]);

        $js = $view->getBodyHtml();
        $css = $view->getHeadHtml();

        return [
            'html' => $html,
            'js' => $js,
            'css' => $css
        ];
    }

    /**
     * Returns a conditional type selection array grouped by category
     *
     * Categories
     * - Standard conditional
     * - Custom conditionals that need to be registered using the Sprout Forms Conditional API
     *
     * @return array
     */
    public function prepareConditionalTypeSelection(): array
    {
        $conditionals = $this->getAllConditionals();
        $standardConditionals = [];

        if (count($conditionals)) {
            // Loop through registered conditionals and add them to the standard group
            foreach ($conditionals as $class => $integration) {
                $standardConditionals[get_class($integration)] = $integration::displayName();
            }

            // Sort fields alphabetically by name
            asort($standardConditionals);

            // Add the group label to the beginning of the standard group
            $standardConditionals = SproutForms::$app->fields->prependKeyValue($standardConditionals, 'standardConditionalsGroup', ['optgroup' => Craft::t('sprout-forms', 'Standard Rules')]);
        }

        return $standardConditionals;
    }

}
