<?php

require_once 'Zend/Pdf/Table.php';

/**
 * Encapsulates common logic for handling creation of PDF files.
 *
 * Currently defined table options:
 *     align => center, justify
 *
 * Currently defined column options:
 *     align => left, right, or center : string
 *     bold => true, false : boolean
 *     indent-left => N : int
 *     border-right => N,N,N : int,int,int - The color of the border, 0-1 scale (not 0-255)
 *     colspan => N : int - Like HTML colspan.
 *     color => N,N,N : int,int,int - The color of the text, 0-1 scale (not 0-255)
 *
 */
class Zend_Pdf
{
    /**
     * Stores the paper size of the final PDF.
     *      Zend_Pdf_Page::SIZE_A4
     *      Zend_Pdf_Page::SIZE_A4_LANDSCAPE
     *      Zend_Pdf_Page::SIZE_LETTER
     *      Zend_Pdf_Page::SIZE_LETTER_LANDSCAPE
     * @var int
     */
    private $_paperSize;

    /**
     * Size of margins on the page. Units are Points.
     *
     * @var int
     */
    private $_sideMargin;
    private $_heightMargin;

    /**
     * The Font to use for text output. Options are:
     *      Zend_Pdf_Font::FONT_COURIER
     *      Zend_Pdf_Font::FONT_COURIER_BOLD
     *      Zend_Pdf_Font::FONT_COURIER_OBLIQUE (identical to Zend_Pdf_Font::FONT_COURIER_ITALIC)
     *      Zend_Pdf_Font::FONT_COURIER_BOLD_OBLIQUE (identical to Zend_Pdf_Font::FONT_COURIER_BOLD_ITALIC)
     *      Zend_Pdf_Font::FONT_HELVETICA
     *      Zend_Pdf_Font::FONT_HELVETICA_BOLD
     *      Zend_Pdf_Font::FONT_HELVETICA_OBLIQUE (identical to Zend_Pdf_Font::FONT_HELVETICA_ITALIC)
     *      Zend_Pdf_Font::FONT_HELVETICA_BOLD_OBLIQUE (identical to Zend_Pdf_Font::FONT_HELVETICA_BOLD_ITALIC)
     *      Zend_Pdf_Font::FONT_SYMBOL
     *      Zend_Pdf_Font::FONT_TIMES_ROMAN
     *      Zend_Pdf_Font::FONT_TIMES
     *      Zend_Pdf_Font::FONT_TIMES_BOLD
     *      Zend_Pdf_Font::FONT_TIMES_ITALIC
     *      Zend_Pdf_Font::FONT_ZAPFDINGBATS
     *
     * @var object
     */
    private $_font;
    private $_fontBold;


    /**
     * The font size to use for text output. Units are Points.
     * The type stores the currently used type.
     *
     * @var int
     */
    private $_fontSize;
    private $_fontType;

    /**
     * Height and Width of the page. Based off the paper size. Units are Points.
     *
     * @var int
     * @var int
     */
    private $_maxHeight;
    private $_maxWidth;

    /**
     * The header image to be loaded as the first element in the resultant PDF.
     *
     * @var string
     */
    private $_headerImage;

    /**
     * The signature image to be added when the report is signed.
     *
     * @var string
     */
    private $_signatureFile;
    private $_signatureDate;
    private $_signatureName;

    /**
     * Stores the meta data for layout of the PDF in [row][column] format.
     *
     * @var array
     */
    private $_tables;

    /**
     * The current working table pointer.
     *
     * @var int
     */
    private $_numTables;

    function Zend_Pdf()
    {
        // Require the PDF class.
        Zend_Loader::loadClass('Zend_Pdf');

        // Setup Initial Variables
        $this->_numTables = 0;
        $this->_tables = array();
        $this->setFont();
        $this->setPaperSize();
        $this->setMargin();
    }

    /**
     * Set Initial Variables. Create the PDF object.
     *
     */
    public function build()
    {
        // Creat the PDF Object.
        $pdf = new Zend_Pdf();

        // First Page
        $currentPage = 0;
        $pdf->pages[$currentPage] = $pdf->newPage($this->_paperSize);

        // Move pointer to the top of the page.
        $currentHeight = $this->_maxHeight;

        // Add the header image.
        if(!empty($this->_headerImage)) {
            $image = Zend_Pdf_Image::imageWithPath($this->_headerImage);

            // Convert from pixels to points.
            $height = $image->getPixelHeight() * 0.75;
            $width = $image->getPixelWidth() * 0.75;

            // If the image is bigger than our space.
            if($width > $this->_maxWidth) {
                $proportion = $width / ($this->_maxWidth - ($this->_sideMargin * 2));
                $width /= $proportion;
                $height /= $proportion;
            }

            // Parameters go in: Left, Bottom, Right, Top : X1, Y2, X2, Y1
            // The offset is how far to shift the image right from 0 to achieve centering on the X axis.
            $offset = ($this->_maxWidth - $width) / 2;
            $x1 = $offset + 0;      $y1 = $this->_maxHeight - ($this->_heightMargin/2);
            $x2 = $offset + $width; $y2 = $y1 - $height;

            // Draw the header.
            $pdf->pages[$currentPage]->drawImage($image, $x1, $y2, $x2, $y1);

            $currentHeight = $y2 - ($this->_fontSize*2);
        } else {
            // If no header, set the first line below the margin.
            $currentHeight = $this->_maxHeight - $this->_heightMargin;
        }

        // Layout all columns.
        foreach($this->_tables as $table) {

            // Maximum usable space for a row.
            $maxWidth = ($this->_maxWidth - ($this->_sideMargin*2));

            // Gather some information about the table.
            $colWidths = $table->getColWidths($this->_font, $this->_fontBold, $maxWidth);

            // Highest number of columns in a single row.
            $numCols = count($colWidths);

            // Amount of horizontal space necessary to draw the table.
            $tableWidth = array_sum($colWidths);

            // Justify the table if the flag is set.
            if($table->getOption('align') == 'justify') {
                $difference = $maxWidth - $tableWidth;
                if($difference > 0 && count($colWidths) > 0) {
                    $addToEach = intval(($difference / count($colWidths)) + 0.5);
                    foreach($colWidths as $num => $value) {
                        $colWidths[$num] = $value + $addToEach;
                    }
                }
            }

            foreach($table as $row) {
                // Center the table if the flag is set.
                if($table->getOption('align') == 'center') {
                    // Calculate the distance between the width of the table and the margins.
                    $difference = ($maxWidth - $tableWidth) / 2;
                    if($difference < 0) {
                        $difference = 0;
                    }
                    $x = $this->_sideMargin + $difference;
                } else {
                    $x = $this->_sideMargin;
                }

                // Wrap the page if necessary.
                if($currentHeight <= ($this->_heightMargin/2)) {
                    $currentPage++;
                    $pdf->pages[$currentPage] = $pdf->newPage($this->_paperSize);
                    $currentHeight = $this->_maxHeight - ($this->_heightMargin);
                }

                // The real key tracks the column to use during colspanned rows.
                $realKey = 0;

                foreach($row as $key => $col) {
                    // Font Size
                    if($col->getOption('size')) {
                        $this->setFont($this->_fontType, $col->getOption('size'));
                    }

                    // Set the font.
                    if($col->getOption('bold')) {
                        $font = $this->_fontBold;
                    } else {
                        $font = $this->_font;
                    }

                    // How far to move it on the X axis for the next column.
                    $offset = $colWidths[$realKey];

                    // Column spanning.
                    // Must calculate before wrapping text.
                    if($col->getOption('colspan')) {
                        $colspan = $col->getOption('colspan');
                        if($colspan > $numCols) {
                            $colspan = $numCols;
                        }
                        $size = 0;
                        for($i = 0; $i < $colspan; $i++) {
                            $index = $realKey + $i;
                            if(isset($colWidths[$index])) {
                                $size += $colWidths[$index];
                            }
                        }
                        $offset = $size;
                        $realKey += $colspan;
                    } else {
                        $realKey++;
                    }

                    // Wrap the text if necessary
                    $text = $this->_wrapText($col->getText(), $offset, $font, $this->_fontSize);
                    $numLines = count($text);

                    // Set Text Color
                    if($col->getOption('color')) {
                        $colors = explode(',', $col->getOption('color'));
                        $pdf->pages[$currentPage]->setFillColor(new Zend_Pdf_Color_Rgb($colors[0], $colors[1], $colors[2]));
                    } else {
                        $pdf->pages[$currentPage]->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
                    }

                    // Set the font to be used.
                    $pdf->pages[$currentPage]->setFont($font, $this->_fontSize);

                    // Safe to add any borders now.
                    // Border-Right
                    if($col->getOption('border-right')) {
                        $colors = explode(',', $col->getOption('border-right'));
                        $pdf->pages[$currentPage]->setLineColor(new Zend_Pdf_Color_Rgb($colors[0],$colors[1],$colors[2]));
                        // Draw the right border.
                        $top = $currentHeight + $this->_fontSize;
                        $pdf->pages[$currentPage]->drawLine($x + $offset, $top, $x + $offset, $currentHeight);
                    }

                    // Draw the text.
                    // Perform the alignment calculations. Has to be done after text-wrapping.
                    $align = $col->getOption('align');
                    $length = $this->_getWidth($col->getText(), $font, $this->_fontSize);
                    $length10 = $this->_getWidth($col->getText(), $font, 10);

                    switch($align) {
                        case 'center':
                            // Center Align
                            $leftBound = $x + (($offset - $length) / 2);
                            break;
                        case 'right':
                            // Right Align
                            $leftBound = $x + (($offset - $length));
                            break;
                        default:
                            // Left Align
                            $leftBound = $x;
                            break;
                    }

                    // Border @todo: make this an option later. Mostly for debuging position.
                    /*$borderHeight = $currentHeight;
                    foreach($text as $key => $line) {
                        $top = $borderHeight + $row->getHeight();
                        $pdf->pages[$currentPage]->drawRectangle($x, $top, $x + $offset, $borderHeight, $fillType = Zend_Pdf_Page::SHAPE_DRAW_STROKE);
                        if($key < ($numLines-1)) {
                            // Move the line pointer down the page.
                            $borderHeight -= $row->getHeight();
                        }
                    }*/

                    // Underline: @todo: make this an option later.
                    //$pdf->pages[$currentPage]->drawLine($x, $currentHeight-1, $x + $offset, $currentHeight-1);

                    // Finally, draw the text in question.
                    $tempHeight = $currentHeight;
                    foreach($text as $key => $line) {
                        $pdf->pages[$currentPage]->drawText($line, $leftBound + $col->getOption('indent-left'), $tempHeight);
                        if($key < ($numLines-1)) {
                            // Move the line pointer down the page.
                            $tempHeight -= $row->getHeight();
                        }
                    }

                    // Move the x-axis cursor, plus any padding.
                    $x += $offset;

                    // Restore Font Size to default.
                    if($col->getOption('size')) {
                        $this->setFont();
                    }
                }

                // Move the line height pointer by the number of actual lines drawn (> 1 when line wrapping).
                if($numLines > 0) {
                    $currentHeight -= $row->getHeight() * $numLines;
                } else {
                    $currentHeight -= $row->getHeight();
                }
            }
        }

        // Add the signature
        if(!empty($this->_signatureFile)) {
            $image = Zend_Pdf_Image::imageWithPath($this->_signatureFile);

            // Convert from pixels to points.
            $height = $image->getPixelHeight() * 0.75;
            $width = $image->getPixelWidth() * 0.75;

            $maxWidth = 150;

            // If the image is bigger than our space.
            if($width > $maxWidth) {
                $proportion = $width / $maxWidth;
                $width /= $proportion;
                $height /= $proportion;
            }

            // Parameters go in: Left, Bottom, Right, Top : X1, Y2, X2, Y1
            // The offset is how far to shift the image right from 0 to achieve centering on the X axis.
            $offset = $this->_sideMargin;
            $x1 = $offset + 0;      $y1 = $currentHeight-5;
            $x2 = $offset + $width; $y2 = $y1 - $height;

            // Draw the signature.
            $pdf->pages[$currentPage]->drawImage($image, $x1, $y2, $x2, $y1);

            $currentHeight = $y2 - ($this->_fontSize);
            $pdf->pages[$currentPage]->drawText($this->_signatureName, $offset, $currentHeight);

            $currentHeight = $y2 - 1 - ($this->_fontSize)*2;
            $pdf->pages[$currentPage]->drawText($this->_signatureDate, $offset, $currentHeight);
        }

        // Save it.
        $pdf->save('../data/pdf/report.pdf');
    }

    /**
     * Sets the font and font size to use for the entire output process.
     * Size units are in points.
     *
     * @param string $name The font type to use
     * @param int $size The font size to use in units of points.
     *
     */
    public function setFont($type = 3, $size = 10)
    {
        $types = array( '1'  => Zend_Pdf_Font::FONT_COURIER,
                        '1b' => Zend_Pdf_Font::FONT_COURIER_BOLD,
                        '2'  => Zend_Pdf_Font::FONT_HELVETICA,
                        '2b' => Zend_Pdf_Font::FONT_HELVETICA_BOLD,
                        '3'  => Zend_Pdf_Font::FONT_TIMES,
                        '3b' => Zend_Pdf_Font::FONT_TIMES_BOLD);

        $this->_font = Zend_Pdf_Font::fontWithName($types[$type]);
        $this->_fontBold = Zend_Pdf_Font::fontWithName($types[$type.'b']);
        $this->_fontType = $type;
        $this->_fontSize = $size;
    }

    /**
     * Sets the font to use for the entire output process.
     * Units are points.
     *
     *  @param int $this->_sideMargin The margin size to use for the left/right sides in units of points.
     *  @param int $this->_heightMargin The margin size to use for the top/bottom in units of points.
     */
    public function setMargin($sideMargin = 36, $heightMargin = 54)
    {
        $this->_sideMargin   = $sideMargin;
        $this->_heightMargin = $heightMargin;
    }

    /**
     * Sets the font to use for the entire output process.
     *
     *
     */
    public function setPaperSize($size = 1)
    {

        $sizes = array( '1' => Zend_Pdf_Page::SIZE_A4,
                        '2' => Zend_Pdf_Page::SIZE_A4_LANDSCAPE,
                        '3' => Zend_Pdf_Page::SIZE_LETTER,
                        '4' => Zend_Pdf_Page::SIZE_LETTER_LANDSCAPE);

        $this->_paperSize = $sizes[$size];
        $hw = explode(":", $this->_paperSize);
        $this->_maxWidth = $hw[0];
        $this->_maxHeight = $hw[1];
    }

    /**
     * Sets the header image to be used in the PDF.
     *
     *
     */
    public function setHeaderImage($filename)
    {
        $this->_headerImage = $filename;
    }

    /**
     * Sets the signature image to be used in the PDF.
     *
     *
     */
    public function setSignatureImage($filename, $date, $name)
    {
        $this->_signatureFile = $filename;
        $this->_signatureDate = $date;
        $this->_signatureName = $name;
    }

    /**
     * Adds a new row to the model with no columns. Moves the row pointer to the new row.
     *
     * @param object $param - An object of type Zend_Pdf_Table
     */
    public function addTable(array $options = array())
    {
        $this->_tables[$this->_numTables] = new Zend_Pdf_Table($options);
        $table = $this->_tables[$this->_numTables];
        $this->_numTables++;

        return $table;
    }

    /**
     * Wraps the given text to the colWidth provided.
     *
     * @param string text - The text to wrap
     * @param int colWidth - The width of a column
     * @param object font - The font to use.
     * @param int fontSize - The font size in use.
     *
     * @return array - An array of wrapped text, one line per row.
     */
    private function _wrapText($text, $colWidth, $font, $fontSize)
    {
        // Return if empty string.
        if(strlen($text) == 0) {
            return array();
        }

        // Find the length of the entire string in points.
        $length = $this->_getWidth($text, $font, $fontSize);
        $length10 = $this->_getWidth($text, $font, 10);

        // Find out the average length of an individual character.
        $avg = intval(($length / strlen($text)) + 0.5);

        // If something is horribly wrong
        if($avg == 0) {
            return array();
        }

        // How many characters to wrap at, given the size of the cell.
        $numToWrap = intval(($colWidth / $avg) + 0.5);

        // Tolerance within 4 characters:
        if(strlen($text) - $numToWrap <= 4) {
            $numToWrap = strlen($text);
        }

        $newText = explode('<br>', wordwrap($text, $numToWrap, '<br>'));

        return $newText;
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