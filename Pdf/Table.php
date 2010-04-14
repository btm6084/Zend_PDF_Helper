<?php

require_once 'ZendPDF_Helper/Pdf/Row.php';

/**
 * Represents a Rowumn in a row in a table in a PDF file.
 */
class ZendPDF_Helper_Pdf_Table implements IteratorAggregate
{
    /**
     * Array containing all Rows in the row.
     *
     * @var array
     */
    private $_rows;

    /**
     * The number of Rows in the row.
     *
     * @var int
     */
    private $_numRows;

    /**
     * Table Options
     *
     * @var array
     */
    private $_options;

    /**
     * Instantiates the row class.
     *
     *
     */
    public function ZendPDF_Helper_Pdf_Table(array $options = array())
    {
        $this->_rows = array();
        $this->_numRows = 0;
        $this->_options = $options;
    }

    /**
     * Adds a new Row to the model. Moves the Row pointer to the next Row.
     *
     *
     */
    public function addRow()
    {
        $this->_rows[$this->_numRows] = new ZendPDF_Helper_Pdf_Row();
        $row = $this->_rows[$this->_numRows];
        $this->_numRows++;

        return $row;
    }

    /**
     * Adds a spacer(empty) row.
     *
     * @param object $table - Object of type ZendPDF_Helper_Pdf_Table to store the results in.
     *
     * @return void
     */
    public function addSpacerRow()
    {
        $this->addRow()->addCol('');
    }

    /**
     * Returns the number of Rows in the row.
     */
    public function getNumRows()
    {
        return $this->_numRows;
    }

    /**
     * Returns the highest column count for a single row
     */
    public function getMaxCols()
    {
        $maxCols = 0;

        foreach($this->_rows as $row) {
            $numCols = $row->getNumCols();
            if($maxCols < $numCols) {
                $maxCols = $numCols;
            }
        }
        return $maxCols;
    }

    /**
     * Returns the columns widths for each column.
     */
    public function getColWidths($font, $fontBold)
    {
        $sizes = array();
        $maxCols = $this->getMaxCols();

        foreach($this->_rows as $row) {
            $realKey = 0;
            foreach($row as $key => $col) {
                $usedFont = $font;
                $options = $col->getOptions();


                if($col->getOption('bold')) {
                    $usedFont = $fontBold;
                }

                if($col->getOption('colspan') && $this->getNumRows() > 2) {
                    $realKey += $col->getOption('colspan');
                    continue;
                }

                $length = $this->_getWidth($col->getText(), $usedFont, $col->getOption('size')) + $col->getOption('indent-left');

                if(isset($sizes[$realKey])){
                    if($sizes[$realKey] < $length) {
                        $sizes[$realKey] = $length + 5;
                    }
                } else {
                    $sizes[$realKey] = $length + 5;
                }

                $realKey++;
            }
        }

        return $sizes;
    }

    /**
     * Returns the options for the cell.
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Returns the specified option value.
     */
    public function getOption($option)
    {
        if(isset($this->_options[$option])) {
            return $this->_options[$option];
        } else {
            return null;
        }
    }

    /**
     * Allows for iteration over individual rows
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_rows);
    }

    /**
     * Returns the width of the string, in points.
     *
     * @param string text - The text to wrap
     * @param object font - The font to use.
     * @param int fontSize - The font size in use.
     *
     */
    private function _getWidth($text, $font, $fontSize)
    {
        // Collect information on each character.
        $characters = array_map('ord', str_split($text));

        // Find out the units being used for the current font.
        $glyphs = $font->glyphNumbersForCharacters($characters);
        $widths = $font->widthsForGlyphs($glyphs);
        $units = $font->getUnitsPerEm();

        foreach($characters as $num => $character) {
            $ratio[$num] = $widths[$num] / $units;
        }

        return intval(array_sum($ratio) * $fontSize);
    }
}