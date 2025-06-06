<?php

namespace Laminas\I18n\Translator;

use ArrayObject;
use Laminas\I18n\Exception;
use Laminas\I18n\Translator\Plural\Rule as PluralRule;

use function array_replace;

/**
 * Text domain.
 *
 * @template TKey of array-key
 * @template TValue
 * @extends ArrayObject<TKey, TValue>
 * @final
 */
class TextDomain extends ArrayObject
{
    /**
     * Plural rule.
     *
     * @var PluralRule|null
     */
    protected $pluralRule;

    /**
     * Default plural rule shared between instances.
     *
     * @var PluralRule|null
     */
    protected static $defaultPluralRule;

    /**
     * Set the plural rule
     *
     * @return $this
     */
    public function setPluralRule(PluralRule $rule)
    {
        $this->pluralRule = $rule;
        return $this;
    }

    /**
     * Get the plural rule.
     *
     * @param  bool $fallbackToDefaultRule
     * @return PluralRule|null
     */
    public function getPluralRule($fallbackToDefaultRule = true)
    {
        if ($this->pluralRule === null && $fallbackToDefaultRule) {
            return static::getDefaultPluralRule();
        }

        return $this->pluralRule;
    }

    /**
     * Checks whether the text domain has a plural rule.
     *
     * @return bool
     */
    public function hasPluralRule()
    {
        return $this->pluralRule !== null;
    }

    /**
     * Returns a shared default plural rule.
     *
     * @return PluralRule
     */
    public static function getDefaultPluralRule()
    {
        if (static::$defaultPluralRule === null) {
            static::$defaultPluralRule = PluralRule::fromString('nplurals=2; plural=n != 1;');
        }

        return static::$defaultPluralRule;
    }

    /**
     * Merge another text domain with the current one.
     *
     * The plural rule of both text domains must be compatible for a successful
     * merge. We are only validating the number of plural forms though, as the
     * same rule could be made up with different expression.
     *
     * @return $this
     * @throws Exception\RuntimeException
     * @template TNewKey of array-key
     * @template TNewValue
     * @param self<TNewKey, TNewValue> $textDomain
     * @psalm-self-out self<TKey|TNewKey, TValue|TNewValue>
     */
    public function merge(TextDomain $textDomain)
    {
        if ($this->hasPluralRule() && $textDomain->hasPluralRule()) {
            if ($this->getPluralRule()->getNumPlurals() !== $textDomain->getPluralRule()->getNumPlurals()) {
                throw new Exception\RuntimeException(
                    'Plural rule of merging text domain is not compatible with the current one'
                );
            }
        } elseif ($textDomain->hasPluralRule()) {
            $this->setPluralRule($textDomain->getPluralRule());
        }

        $this->exchangeArray(
            array_replace(
                $this->getArrayCopy(),
                $textDomain->getArrayCopy()
            )
        );

        return $this;
    }
}
