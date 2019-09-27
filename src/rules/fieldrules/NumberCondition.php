<?php

namespace barrelstrength\sproutforms\rules\fieldrules;

use barrelstrength\sproutforms\base\BaseCondition;
use barrelstrength\sproutforms\base\ConditionalType;
use barrelstrength\sproutforms\rules\conditions\IsCondition;
use barrelstrength\sproutforms\rules\conditions\IsGreaterThanCondition;
use barrelstrength\sproutforms\rules\conditions\IsGreaterThanOrEqualToCondition;
use barrelstrength\sproutforms\rules\conditions\IsLessThanCondition;
use barrelstrength\sproutforms\rules\conditions\IsLessThanOrEqualToCondition;

/**
 *
 * @property array|\barrelstrength\sproutforms\base\BaseCondition[] $rules
 * @property string                                                 $type
 */
class NumberCondition extends ConditionalType
{
    public function getType(): string
    {
        return 'number';
    }

    /**
     * @return BaseCondition[]
     */
    public function getRules(): array
    {
        return [
            new IsCondition(['formField' => $this->formField]),
            new IsGreaterThanCondition(['formField' => $this->formField]),
            new IsLessThanCondition(['formField' => $this->formField]),
            new IsGreaterThanOrEqualToCondition(['formField' => $this->formField]),
            new IsLessThanOrEqualToCondition(['formField' => $this->formField]),
        ];
    }
}