<?php

/**
 * Represents a column in a row in a table in a PDF file.
 */
class ZendPDF_Helper_Pdf_Col
{
    /**
     * The text value of the column
     *
     * @var string
     */
    private $_text;

    /**
     * Column Options
     *
     * @var array
     */
    private $_options;

    /**
     * Initialize the column
     *
     */
    public function ZendPDF_Helper_Pdf_Col($text, array $options = array())
    {
        if(!isset($options['size'])) {
            $options['size'] = 10;       // Default to 10
        }

        $this->_text     = $text;
        $this->_options  = $options;
    }

    /**
     * Sets the text for the column
     *
     * @param string $text - The text to display in the column.
     */
    public function getText()
    {
        return $this->_text;
    }

    /**
     * Returns the options for the cell.
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Returns the options for the cell.
     */
    public function getOption($option)
    {
        if(isset($this->_options[$option])) {
            return $this->_options[$option];
        } else {
            return null;
        }
    }
}