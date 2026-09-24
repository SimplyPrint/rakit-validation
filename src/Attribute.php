<?php

namespace Rakit\Validation;

class Attribute
{

    /** @var array */
    protected $rules = [];

    /** @var string */
    protected $key;

    /** @var string|null */
    protected $alias;

    /** @var \Rakit\Validation\Validation */
    protected $validation;

    /** @var bool */
    protected $required = false;

    /** @var \Rakit\Validation\Validation|null */
    protected $primaryAttribute = null;

    /** @var array */
    protected $otherAttributes = [];

    /**
     * Sibling attributes of one wildcard expansion, INCLUDING $this. Shared by
     * every sibling, so the expansion costs one array rather than one per
     * attribute; getOtherAttributes() removes $this on demand.
     *
     * @var array|null
     */
    protected $siblingAttributes = null;

    /** @var int|string|null Index of $this within $siblingAttributes */
    protected $siblingIndex = null;

    /** @var array */
    protected $keyIndexes = [];

    /**
     * Constructor
     *
     * @param \Rakit\Validation\Validation  $validation
     * @param string      $key
     * @param string|null $alias
     * @param array       $rules
     * @return void
     */
    public function __construct(
        Validation $validation,
        string $key,
        $alias = null,
        array $rules = []
    ) {
        $this->validation = $validation;
        $this->alias = $alias;
        $this->key = $key;
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * Set the primary attribute
     *
     * @param \Rakit\Validation\Attribute $primaryAttribute
     * @return void
     */
    public function setPrimaryAttribute(Attribute $primaryAttribute)
    {
        $this->primaryAttribute = $primaryAttribute;
    }

    /**
     * Set key indexes
     *
     * @param array $keyIndexes
     * @return void
     */
    public function setKeyIndexes(array $keyIndexes)
    {
        $this->keyIndexes = $keyIndexes;
    }

    /**
     * Get primary attributes
     *
     * @return \Rakit\Validation\Attribute|null
     */
    public function getPrimaryAttribute()
    {
        return $this->primaryAttribute;
    }

    /**
     * Set other attributes
     *
     * @param array $otherAttributes
     * @return void
     */
    public function setOtherAttributes(array $otherAttributes)
    {
        $this->otherAttributes = [];
        foreach ($otherAttributes as $otherAttribute) {
            $this->addOtherAttribute($otherAttribute);
        }
    }

    /**
     * Add other attributes
     *
     * @param \Rakit\Validation\Attribute $otherAttribute
     * @return void
     */
    public function addOtherAttribute(Attribute $otherAttribute)
    {
        $this->otherAttributes[] = $otherAttribute;
    }

    /**
     * Share one sibling set across a whole wildcard expansion.
     *
     * Giving each attribute its own copy of the other N-1 made a wildcard rule
     * quadratic in memory: N arrays of N entries, all live at once. A 6,000-point
     * `outline.*.*` cost 3 GB that way. Assigning the same array to every sibling
     * is copy-on-write, so the set is stored once; the per-attribute view is built
     * only if something actually asks for it.
     *
     * @param array      $siblings All expanded attributes, including $this
     * @param int|string $index    Key of $this within $siblings
     * @return void
     */
    public function setSiblingAttributes(array $siblings, $index)
    {
        $this->siblingAttributes = $siblings;
        $this->siblingIndex = $index;
        $this->otherAttributes = [];
    }

    /**
     * Get other attributes
     *
     * @return array
     */
    public function getOtherAttributes(): array
    {
        if ($this->siblingAttributes === null) {
            return $this->otherAttributes;
        }

        $others = $this->siblingAttributes;
        unset($others[$this->siblingIndex]);

        // Reindexed, matching what setOtherAttributes() produced.
        return array_values($others);
    }

    /**
     * Add rule
     *
     * @param \Rakit\Validation\Rule $rule
     * @return void
     */
    public function addRule(Rule $rule)
    {
        $rule->setAttribute($this);
        $rule->setValidation($this->validation);
        $this->rules[$rule->getKey()] = $rule;
    }

    /**
     * Get rule
     *
     * @param string $ruleKey
     * @return void
     */
    public function getRule(string $ruleKey)
    {
        return $this->hasRule($ruleKey)? $this->rules[$ruleKey] : null;
    }

    /**
     * Get rules
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Check the $ruleKey has in the rule
     *
     * @param string $ruleKey
     * @return bool
     */
    public function hasRule(string $ruleKey): bool
    {
        return isset($this->rules[$ruleKey]);
    }

    /**
     * Set required
     *
     * @param boolean $required
     * @return void
     */
    public function setRequired(bool $required)
    {
        $this->required = $required;
    }

    /**
     * Set rule is required
     *
     * @return boolean
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get key
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get key indexes
     *
     * @return array
     */
    public function getKeyIndexes(): array
    {
        return $this->keyIndexes;
    }

    /**
     * Get value
     *
     * @param string|null $key
     * @return mixed
     */
    public function getValue(?string $key = null)
    {
        if ($key && $this->isArrayAttribute()) {
            $key = $this->resolveSiblingKey($key);
        }

        if (!$key) {
            $key = $this->getKey();
        }

        return $this->validation->getValue($key);
    }

    /**
     * Get that is array attribute
     *
     * @return boolean
     */
    public function isArrayAttribute(): bool
    {
        return count($this->getKeyIndexes()) > 0;
    }

    /**
     * Check this attribute is using dot notation
     *
     * @return boolean
     */
    public function isUsingDotNotation(): bool
    {
        return strpos($this->getKey(), '.') !== false;
    }

    /**
     * Resolve sibling key
     *
     * @param string $key
     * @return string
     */
    public function resolveSiblingKey(string $key): string
    {
        $indexes = $this->getKeyIndexes();
        $keys = explode("*", $key);
        $countAsterisks = count($keys) - 1;
        if (count($indexes) < $countAsterisks) {
            $indexes = array_merge($indexes, array_fill(0, $countAsterisks - count($indexes), "*"));
        }
        $args = array_merge([str_replace("*", "%s", $key)], $indexes);
        return call_user_func_array('sprintf', $args);
    }

    /**
     * Get humanize key
     *
     * @return string
     */
    public function getHumanizedKey()
    {
        $primaryAttribute = $this->getPrimaryAttribute();
        $key = str_replace('_', ' ', $this->key);

        // Resolve key from array validation
        if ($primaryAttribute) {
            $split = explode('.', $key);
            $key = implode(' ', array_map(function ($word) {
                if (is_numeric($word)) {
                    $word = $word + 1;
                }
                return Helper::snakeCase($word, ' ');
            }, $split));
        }

        return ucfirst($key);
    }

    /**
     * Set alias
     *
     * @param string $alias
     * @return void
     */
    public function setAlias(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * Get alias
     *
     * @return string|null
     */
    public function getAlias()
    {
        return $this->alias;
    }
}
