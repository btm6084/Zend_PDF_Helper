<?php

//require_once 'Framework/Pdf/Col.php';

/**
 * Represents a column in a row in a table in a PDF file.
 */
class Framework_Pdf_Row implements IteratorAggregate
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
	 * Column Options
	 *
	 * @var array
	 */
	private $_options;


	/**
	 * Instantiates the row class.
	 *
	 *
	 */
	public function Framework_Pdf_Row(array $options = array())
	{
		$this->_cols = array();
		$this->_numCols = 0;

		$this->_options = $options;
	}

	/**
	 * Adds a new col to the model. Moves the col pointer to the next col.
	 *
	 *
	 */
	public function addCol($text = "", $options = array())
	{
		$this->_cols[$this->_numCols] = new Framework_Pdf_Col($text, $options);

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

		// We reduce the size calculated by 10% to save on vertical space.
		return $maxHeight * 1.1;
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