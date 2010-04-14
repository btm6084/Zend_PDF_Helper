<?php

require_once 'ZendPDF_Helper/Pdf/Col.php';

/**
 * Represents a column in a row in a table in a PDF file.
 */
class ZendPDF_Helper_Pdf_Row implements IteratorAggregate
{
    /**
     * Array containing all columns in the row.
     *
     * @var array
     */
    private $_cols;

    /**
     * The number of columns in the row.
     *
     * @var int
     */
    private $_numCols;


    /**
     * Instantiates the row class.
     *
     *
     */
    public function ZendPDF_Helper_Pdf_Row()
    {
        $this->_cols = array();
        $this->_numCols = 0;
    }

    /**
     * Adds a new col to the model. Moves the col pointer to the next col.
     *
     *
     */
    public function addCol($text = "", $options = array())
    {
        $this->_cols[$this->_numCols] = new ZendPDF_Helper_Pdf_Col($text, $options);

        $this->_numCols++;

        return $this;
    }

    /**
     * Gets the number of columns.
     *
     * @return $_numCols
     */
    public function getNumCols()
    {
        return $this->_numCols;
    }

    /**
     * Returns the maximum height needed for the row.
     */
    public function getHeight()
    {
        $maxHeight = 0;
        foreach($this->_cols as $col) {
            if($maxHeight <= $col->getOption('size')) {
                $maxHeight = $col->getOption('size');
            }
        }

        // We reduce the size calculated by 20% to save on vertical space.
        return $maxHeight * 0.8;
    }

    /**
     * Allows for iteration over individual columns
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_cols);
    }
}