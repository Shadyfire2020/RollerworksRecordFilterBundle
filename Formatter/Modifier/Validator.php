<?php

/**
 * This file is part of the RollerworksRecordFilterBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\Bundle\RecordFilterBundle\Formatter\Modifier;

use Rollerworks\Bundle\RecordFilterBundle\Formatter\FormatterInterface;
use Rollerworks\Bundle\RecordFilterBundle\Type\FilterTypeInterface;
use Rollerworks\Bundle\RecordFilterBundle\Value\FilterValuesBag;
use Rollerworks\Bundle\RecordFilterBundle\FilterField;
use Rollerworks\Bundle\RecordFilterBundle\Value\Range;
use Rollerworks\Bundle\RecordFilterBundle\MessageBag;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Validates and formats filter values.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class Validator implements ModifierInterface
{
    /**
     * @var boolean
     */
    protected $isError = false;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getModifierName()
    {
        return 'validator';
    }

    /**
     * {@inheritdoc}
     */
    public function modFilters(FormatterInterface $formatter, MessageBag $messageBag, FilterField $filterConfig, FilterValuesBag $filterStruct, $groupIndex)
    {
        if (!$filterConfig->hasType()) {
            return true;
        }

        $this->isError = false;

        $ranges = $excludedValues = $singleValues = array();
        $type = $filterConfig->getType();

        foreach ($filterStruct->getSingleValues() as $value) {
            if (!$this->validateValue($type, $value->getValue(), '"' . $value->getValue() . '"', $messageBag)) {
                continue;
            }

            $sanitizedValue = $type->sanitizeString($value->getValue());
            $_value         = $sanitizedValue;

            if (!is_scalar($sanitizedValue)) {
                $_value = $type->dumpValue($sanitizedValue);
            }

            $singleValues[] = $_value;
            $value->setValue($sanitizedValue);
        }

        foreach ($filterStruct->getExcludes() as $value) {
            if (!$this->validateValue($type, $value->getValue(), '!"' . $value->getValue() . '"', $messageBag)) {
                continue;
            }

            $sanitizedValue = $type->sanitizeString($value->getValue());
            $_value         = $sanitizedValue;

            if (!is_scalar($sanitizedValue)) {
                $_value = $type->dumpValue($sanitizedValue);
            }

            if (in_array($_value, $singleValues)) {
                $messageBag->addError('record_filter.value_in_include', array('{{ value }}' => '!"' . $value->getOriginalValue() . '"'));
                $this->isError = true;
            }

            $excludedValues[] = $_value;
            $value->setValue($sanitizedValue);
        }

        foreach ($filterStruct->getRanges() as $range) {
            if (
                !$this->validateValue($type, $range->getLower(), self::getRangeQuoted($range), $messageBag)
            ||
                !$this->validateValue($type, $range->getUpper(), self::getRangeQuoted($range), $messageBag)
            ) {
                continue;
            }

            $range->setLower($type->sanitizeString($range->getLower()));
            $range->setUpper($type->sanitizeString($range->getUpper()));

            $this->validateRange($type, $range, $messageBag);

            $ranges[] = $type->dumpValue($range->getLower()) . '-' . $type->dumpValue($range->getUpper());
        }

        foreach ($filterStruct->getExcludedRanges() as $range) {
            if (
                !$this->validateValue($type, $range->getLower(), '!' . self::getRangeQuoted($range), $messageBag)
            ||
                !$this->validateValue($type, $range->getUpper(), '!' . self::getRangeQuoted($range), $messageBag)
            ) {
                continue;
            }

            $range->setLower($type->sanitizeString($range->getLower()));
            $range->setUpper($type->sanitizeString($range->getUpper()));

            $this->validateRange($type, $range, $messageBag);

            $_value = $type->dumpValue($range->getLower()) . '-' . $type->dumpValue($range->getUpper());

            if (in_array($_value, $ranges)) {
                $messageBag->addError('record_filter.range_same_as_excluded', array('{{ value }}' => self::getRangeQuoted($range)));
                $this->isError = true;
            }
        }

        foreach ($filterStruct->getCompares() as $compare) {
            if (!$this->validateValue($type, $compare->getValue(), $compare->getOperator() . '"' . $compare->getValue() . '"', $messageBag)) {
                continue;
            }

            $compare->setValue($type->sanitizeString($compare->getValue()));
        }

        return !$this->isError;
    }

    /**
     * Returns the 'original' range values quoted.
     *
     * @param Range $range
     * @param Range $range2
     *
     * @return string
     */
    protected static function getRangeQuoted(Range $range, Range $range2 = null)
    {
        if (null === $range2) {
            $range2 = $range;
        }

        return '"' . $range->getOriginalLower() . '"-"' . $range2->getOriginalUpper() . '"';
    }

    /**
     * Validates a single-value.
     *
     * @param FilterTypeInterface $type
     * @param string              $value
     * @param string              $originalValue
     * @param MessageBag          $messageBag
     *
     * @return boolean
     *
     * @throws \UnexpectedValueException when the message is not scalar
     */
    protected function validateValue(FilterTypeInterface $type, $value, $originalValue, MessageBag $messageBag)
    {
        $validationMessageBag = clone $messageBag;
        $type->validateValue($value, $validationMessageBag);

        if ($validationMessageBag->has(MessageBag::MSG_ERROR)) {
            // XXX This should be made configurable, maybe following the principle of the Validator Component?
            $message = implode("\n", $validationMessageBag->get('error'));

            $messageBag->addError('record_filter.validation_warning', array(
                '{{ value }}' => $originalValue,
                '{{ msg }}'   => $message
            ));

            $this->isError = true;

            return false;
        }

        return true;
    }

    /**
     * Validates an range-value.
     *
     * @param FilterTypeInterface $type
     * @param Range               $range
     * @param MessageBag          $messageBag
     */
    protected function validateRange(FilterTypeInterface $type, Range $range, MessageBag $messageBag)
    {
        if (!$type->isLower($range->getLower(), $range->getUpper())) {
            $messageBag->addError('record_filter.range_not_lower', array(
                '{{ value1 }}' => $range->getOriginalLower(),
                '{{ value2 }}' => $range->getOriginalUpper(),
            ));

            $this->isError = true;
        }
    }
}
