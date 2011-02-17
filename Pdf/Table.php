<?php

require_once 'Zend/Pdf/Row.php';

/**
 * Represents a Rowumn in a row in a table in a PDF file.
 */
class Zend_Pdf_Table implements IteratorAggregate
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
    public function Zend_Pdf_Table(array $options = array())
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
        $this->_rows[$this->_numRows] = new Zend_Pdf_Row();
        $row = $this->_rows[$this->_numRows];
        $this->_numRows++;

        return $row;
    }

    /**
     * Adds a spacer(empty) row.
     *
     * @param object $table - Object of type Zend_Pdf_Table to store the results in.
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
     * You must pass in the Normal and Bold Zend_Pdf_Font objects for the current font
     * in order to determine the width of characters.
     *
     * @var $font - Normal, non-bolded font.
     * @var $fontBold - Bolded font.
     * @var $maxWidth - The maximim drawing width of the page.
     */
    public function getColWidths($font, $fontBold, $maxWidth)
    {
        $sizes = array();
        $maxCols = $this->getMaxCols();

        foreach($this->_rows as $row) {
            $realKey = 0;
            foreach($row as $key => $col) {
                $usedFont = $font;
                $options = $col->getOptions();

                // If using a bold weight font, the widths change.
                if($col->getOption('bold')) {
                    $usedFont = $fontBold;
                }

                $length = $this->_getWidth($col->getText(), $usedFont, $col->getOption('size')) + $col->getOption('indent-left');

                // If we are doing a colspan for this column, move the column pointer forward the number spanned.
                if($col->getOption('colspan') && $this->getNumRows() > 2) {
                    // Number of columns to be spanned.
                    $numSpanned = $col->getOption('colspan');

                    // For each spanned column, take the total length and divide by the number spanned. That becomes the width of
                    // each spanned column.
                    for($i = 0; $i < $numSpanned; $i++) {
                        $keySim = $realKey + $i;
                        $partLength = intval($length / $numSpanned);

                        if(isset($sizes[$keySim])){
                            if($sizes[$keySim] < $partLength) {
                                $sizes[$keySim] = $partLength + 5;
                            }
                        } else {
                            $sizes[$keySim] = $partLength + 5;
                        }
                    }
                    // Advance the realkey pointer past the spanned columns.
                    $realKey += $col->getOption('colspan');
                } else {
                    // Otherwise, no spanning being done, so we can use straight length.
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
        }

        // We need to account for text wrapping. To do this, we need to make sure the total width
        // does not exceed the allowable space. We calculate how much the line is over, then divide that
        // by the number of columns in the row. This tells us how much space to remove from each column.

        $totalWidth = array_sum($sizes);
        $difference = 0;
        $i = 0;
        while($totalWidth > $maxWidth) {
            $i++;
            // The amount to remove from each column.
            $difference = intval(($totalWidth - $maxWidth) / $maxCols);
            $maxPerCell = intval($maxWidth / $maxCols);

            // We want to stop the situation where $difference is 0, but $totalWidth > $maxWidth
            // Example happens when $totalWidth = 525 and $maxWidth = 523 with 3 columns.
            // 525-523 = 2. 2/3 gives an intval of 0.
            if($difference == 0 && $totalWidth > $maxWidth) {
                $difference = 1;
            }

            if($difference > 0) {
                foreach($sizes as $key => $value) {
                    // Don't allow it to set a size <= 0
                    if($value > $maxPerCell) {
                        $sizes[$key] = $value - $difference;
                    }
                }
                $totalWidth = array_sum($sizes);
            }
            // Protection against an infinite loop.
            if($i > 1000) {
                die("An error has occured. Please contact your administrator.");
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
        $characters2 = str_split($text);
        $characters = array_map('ord', str_split($text));

        // Find out the units being used for the current font.
        $glyphs = $font->glyphNumbersForCharacters($characters);
        $widths = $font->widthsForGlyphs($glyphs);
        //$units  = ($font->getUnitsPerEm() * $fontSize) / 10;
        $units  = $font->getUnitsPerEm();

        // Calculate the length of the string.
        $length = intval((array_sum($widths) / $units) + 0.5) * $fontSize;

        foreach($characters as $num => $character) {
            $ratio[$num] = $widths[$num] / $units;
        }

        return intval(array_sum($ratio) * $fontSize);
        //return $length;
    }
}