<?php
/**
 * Emakina
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Cueillette's project to newer
 * versions in the future.
 *
 * @category    Cueillette
 * @package     Cueillette
 * @copyright   Copyright (c) 2017 Emakina. (http://www.emakina.fr)
 */

namespace AppBundle\Services\Cueillette;

use AppBundle\Entity\Automaton;

/**
 * Class CueilletteSpreadsheet
 *
 * @category    Cueillette
 * @author      <mgi@emakina.fr>
 */
class CueilletteSpreadsheet
{
    const NB_ROWS = 42;

    /** @var string the spreadSheetId */
    private $spreadsheetId;
    /** @var int the current WorkSheetId */
    private $sheetId;
    /** @var  string path to credentials file */
    private $credentialPath;
    /** @var  string Google Application Name */
    private $ggApplicationName;
    /** @var  string Google SpreadSheet Scope */
    private $ggScopes;
    /** @var  string path to Secret Auth Config file */
    private $ggSecretAuthConfig;
    /** @var  string spreadsheet editors */
    private $ggSpreadSheetEditors;

    /** @var \Google_Client $ggClient */
    private $ggClient = null;

    /** @var  \Google_Service_Sheets sheets service */
    private $ggService = null;

    /** @var array the Spreadsheet Dimensions array(nbRows, nbColumns, rowsValuesOrigin, colsValuesOrigin) */
    private $spreadsheetDimension = array();

    /** @var array headers on the left */
    private $leftHeaders = array("Réglé ?", "Trigramme ↓", "Paiement ↓", "Total ↓");

    /**
     * CueilletteSpreadsheet constructor.
     *
     * @param string $spreadsheetId
     * @param string $credentialPath
     * @param string $applicationName
     * @param string $scope
     * @param string $authConfigFile
     * @param string $editors
     */
    public function __construct($spreadsheetId, $credentialPath, $applicationName, $scope, $authConfigFile, $editors)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->credentialPath = $credentialPath;
        $this->ggApplicationName = $applicationName;
        $this->ggScopes = $scope;
        $this->ggSecretAuthConfig = $authConfigFile;
        $this->ggSpreadSheetEditors = new \Google_Service_Sheets_Editors(array("users" => $editors));

        $this->ggClient = new \Google_Client();
        $this->ggClient->setApplicationName($this->ggApplicationName);

        $this->ggClient->setScopes($this->ggScopes);
        $this->ggClient->setAuthConfig($this->expandHomeDirectory($this->ggSecretAuthConfig));
        $this->ggClient->setAccessType('offline');
    }

    /**
     * Sets the spreadsheetId
     *
     * @param string $spreadsheetId
     */
    public function setSpreadSheetId($spreadsheetId)
    {
        $this->spreadsheetId = $spreadsheetId;
    }

    /**
     * Expands the home directory alias '~' to the full path.
     *
     * @param string $path the path to expand.
     *
     * @return string the expanded path.
     */
    private function expandHomeDirectory($path)
    {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }

        return str_replace('~', realpath($homeDirectory), $path);
    }

    public function getGGAuthUrl()
    {
        return $this->ggClient->createAuthUrl();
    }

    public function setAccessToken($authCode, $credentialsPath)
    {
        if (!empty($authCode) && !empty($credentialsPath)) {
            // Exchange authorization code for an access token.
            $accessToken = $this->ggClient->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if (!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
        }
    }

    public function setAutomaton(Automaton $automaton)
    {
        $this->spreadsheetId = $automaton->getSpreadsheetId();
        $this->credentialPath = $automaton->getGgCredentialFile();
    }

    /**
     * Initializes a Google Service Sheets object thanks to the authentication informations
     */
    private function authenticate()
    {
        // Load previously authorized credentials from a file.
        $credentialsPath = $this->expandHomeDirectory($this->credentialPath);
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } elseif (php_sapi_name() === 'cli') {  //if we are on cli mode, we ask the user for the authcode
            // Request authorization from the user.
            $authUrl = $this->ggClient->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $this->ggClient->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if (!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
        } else {
            throw new \Exception("No credential file set");
        }
        $this->ggClient->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($this->ggClient->isAccessTokenExpired()) {
            $refreshToken = $this->ggClient->getRefreshToken();
            $this->ggClient->refreshToken($refreshToken);
            $newAccessToken = $this->ggClient->getAccessToken();
            $newAccessToken['refresh_token'] = $refreshToken;
            file_put_contents($credentialsPath, json_encode($newAccessToken));
        }

        $this->ggService = new \Google_Service_Sheets($this->ggClient);
    }

    /**
     * Returns the sheet's title for the current week
     *
     * @return string
     */
    private function getSheetTitle()
    {
        //selects "this week" if today is monday or "next week" if we are another day
        $weekSelection = ((date('w', time()) === '1') ? 'this' : 'next') . ' week';

        return date('j/n/y', strtotime("monday $weekSelection")) . " au " . date(
                'j/n/y',
                strtotime("sunday $weekSelection")
            );
    }

    private function getCurrentWeekSheet()
    {
        $sheetTitle = $this->getSheetTitle();

        /** @var \Google_Service_Sheets_Spreadsheet $spreadsheet */
        $spreadsheet = $this->ggService->spreadsheets->get($this->spreadsheetId);
        $sheets = $spreadsheet->getSheets();

        /** @var \Google_Service_Sheets_Sheet $selectedSheet */
        $selectedSheet = null;
        $length = count($sheets);
        for ($i = 0; $i < $length; $i++) {
            /** @var \Google_Service_Sheets_Sheet $sheet */
            $sheet = $sheets[$i];
            if ($sheet->getProperties()->getTitle() == $sheetTitle) {
                $selectedSheet = $sheet;

                break;
            }
        }

        return $selectedSheet;
    }

    public function setCredentialPath($credentialPath)
    {
        $this->credentialPath = $credentialPath;
    }

    /**
     * Creates a new sheet for the current week with the given products
     * (Skips if the sheet already exists)
     *
     * @param array $products
     * @param boolean $force to overwrite already existing sheet for the current week
     */
    public function importProducts(array $products, $force = false)
    {
        $this->authenticate();

        $currentWeekSheet = $this->getCurrentWeekSheet();
        if ($currentWeekSheet && $force) {
            $this->deleteSheet($currentWeekSheet->getProperties()->getSheetId());

            unset($currentWeekSheet);
            $currentWeekSheet = null;
        }

        $sheetTitle = $this->getSheetTitle();

        if ($currentWeekSheet == null && !empty($products)) {
            //nb Columns = nb products + nb left headers + 1 for the comments
            $this->spreadsheetDimension = array(
                self::NB_ROWS,
                (count($products) + count($this->leftHeaders) + 1),
                5,
                $this->getLetterForColumn(count($this->leftHeaders))
            );

            $this->createSheet($sheetTitle, $this->spreadsheetDimension[0], $this->spreadsheetDimension[1]);

            $this->pushHeaders($sheetTitle, $products);

            $this->setFormat();
        }
    }

    /**
     * Read the current week spreadsheet and returns the list of products to buy
     *
     * @return array
     */
    public function getProductsToBuy()
    {
        $productsToBuy = array();
        $this->authenticate();

        $currentWeekSheet = $this->getCurrentWeekSheet();
        if ($currentWeekSheet) {
            $firstColumn = $this->getLetterForColumn(count($this->leftHeaders));
            $lastColumn = $this->getLetterForColumn(
                $currentWeekSheet->getProperties()->getGridProperties()->getColumnCount() - 2
            );

            $products = $this->ggService->spreadsheets_values->get(
                $this->spreadsheetId,
                $this->getSheetTitle() . '!' . $firstColumn . '1:' . $lastColumn . '1',
                array('valueRenderOption' => 'FORMULA')
            );
            $quantities = $this->ggService->spreadsheets_values->get(
                $this->spreadsheetId,
                $this->getSheetTitle() . '!' . $firstColumn . '4:' . $lastColumn . '4'
            );

            if (!empty($products)) {
                $products = $products->values[0];
                $quantities = $quantities->values[0];
                $length = count($quantities);
                for ($i = 0; $i < $length; $i++) {
                    $qty = $quantities[$i];
                    if ($qty !== "") {
                        $product = $products[$i];

                        $id = -1;

                        preg_match('/\/(\d+)-.*\.html/', $product, $matches);
                        if (!empty($matches)) {
                            $id = $matches[1];
                        }

                        $productsToBuy [] = array('id' => $id, 'qty' => $qty);
                    }
                }
            }
        }

        return $productsToBuy;
    }

    /**
     * Deletes a Sheet
     *
     * @param int $sheetId
     */
    private function deleteSheet($sheetId)
    {
        $deleteRequest = array(
            new \Google_Service_Sheets_Request(
                array(
                    'deleteSheet' => array(
                        'sheet_id' => $sheetId
                    )
                )
            )
        );

        $this->ggService->spreadsheets->batchUpdate(
            $this->spreadsheetId,
            new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(
                array(
                    'requests' => $deleteRequest
                )
            )
        );
    }

    /**
     * Creates a new Sheet
     *
     * @param string $title
     * @param int $nbRows
     * @param int $nbColumns
     */
    private function createSheet($title, $nbRows, $nbColumns)
    {
        $requests = array(
            new \Google_Service_Sheets_Request(
                array(
                    'addSheet' => array(
                        'properties' => array(
                            'index'          => 0,
                            'title'          => $title,
                            'gridProperties' => [
                                'rowCount'    => $nbRows,
                                'columnCount' => $nbColumns,
                            ],
                        )
                    )
                )
            )
        );

        $response = $this->ggService->spreadsheets->batchUpdate(
            $this->spreadsheetId,
            new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(
                array(
                    'requests' => $requests
                )
            )
        );

        $replies = $response->getReplies();
        /** @var \Google_Service_Sheets_Response $addSheetReply */
        $addSheetReply = $replies[0];

        $this->sheetId = $addSheetReply->getAddSheet()->getProperties()->getSheetId();
    }

    /**
     * Returns the column's letter for a given column index
     *
     * @param int $number
     *
     * @return string
     */
    private function getLetterForColumn($number)
    {
        return chr(ord('A') + $number);
    }

    /**
     * Put the headers in the current sheet
     *
     * @param string $sheetTitle
     * @param array $products
     */
    private function pushHeaders($sheetTitle, array $products)
    {
        $maxRows = $this->spreadsheetDimension[0];

        $leftHeadersLength = count($this->leftHeaders);

        $row1 = array_fill(0, $leftHeadersLength, '');
        $row2 = array_fill(0, $leftHeadersLength, '');
        $row3 = $this->leftHeaders;
        $row4 = array_fill(0, ($leftHeadersLength - 1), '');
        $row4[] = "Quantité Totale →";

        $rowsValuesOrigin = $this->spreadsheetDimension[2];
        $colsValuesOrigin = $this->spreadsheetDimension[3];

        $currentColumn = $colsValuesOrigin;
        $maxColumn = $this->getLetterForColumn($leftHeadersLength + count($products) + 1);
        foreach ($products as $product) {
            $row1[] =
                !empty($product['url']) ? "=HYPERLINK(\"${product['url']}\";\"${product['name']}\")" : $product['name'];
            $row2[] = $product["description"];
            $row3[] = $product["price"];
            $range = "${currentColumn}${rowsValuesOrigin}:${currentColumn}" . self::NB_ROWS;
            $row4[] = "=IF(SUM($range)=0;\"\";SUM($range))";
            $currentColumn++;
        }

        $row1[] = '';
        $row2[] = 'Commentaires';
        $row3[] = '';
        $row4[] = '';

        $header = array(
            $row1,
            $row2,
            $row3,
            $row4
        );

        $data = array();
        $data[] = new \Google_Service_Sheets_ValueRange(
            array(
                'range'  => $sheetTitle . "!A1:" . $maxColumn . $leftHeadersLength,
                'values' => $header
            )
        );

        $values = array();
        $pricesRow = $rowsValuesOrigin - 2;
        $maxColumn = $this->getLetterForColumn($leftHeadersLength + (count($products) - 1));
        for ($i = $rowsValuesOrigin; $i <= $maxRows; $i++) {
            $range = "$colsValuesOrigin$pricesRow:$maxColumn$pricesRow;$colsValuesOrigin$i:$maxColumn$i";
            $values[] = array(
                "=IF(OR(D$i=\"\"; D$i=0); \"\"; HYPERLINK(CONCATENATE(\"https://www.paypal.com/myaccount/transfer/send/external?recipient=mgi%40emakina.fr&currencyCode=EUR&amount=\"; SUBSTITUTE(D$i;\",\";\".\")); IMAGE(\"https://www.paypalobjects.com/images/shared/paypal-logo-129x32.svg\"; 1)))",
                "=IF(SUMPRODUCT($range)=0;\"\";SUMPRODUCT($range))"
            );
        }
        $data[] = new \Google_Service_Sheets_ValueRange(
            array(
                'range'  => $sheetTitle . "!C$rowsValuesOrigin:D" . $maxRows,
                'values' => $values
            )
        );

        $body = new \Google_Service_Sheets_BatchUpdateValuesRequest(
            array(
                'valueInputOption' => "USER_ENTERED",
                'data'             => $data
            )
        );
        $result = $this->ggService->spreadsheets_values->batchUpdate($this->spreadsheetId, $body);
    }

    /**
     * Sets the grid format (style + protected ranges)
     */
    private function setFormat()
    {
        $requests = array_merge($this->getStylesRequests(), $this->getProtectedRangesRequests());

        $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(
            array(
                'requests' => $requests
            )
        );

        $response = $this->ggService->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
    }

    /**
     * Returns a new Google_Service_Sheets_Color
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param float $alpha
     *
     * @return \Google_Service_Sheets_Color
     */
    private function getColor($red = 0, $green = 0, $blue = 0, $alpha = 1.)
    {
        return new \Google_Service_Sheets_Color(
            array("red" => $red, "green" => $green, "blue" => $blue, "alpha" => $alpha)
        );
    }

    /**
     * Converts CSS color pattern into a Google_Service_Sheets_Color
     *
     * @param string $hex
     *
     * @return \Google_Service_Sheets_Color
     */
    private function getColorFromCss($hex)
    {
        $hex = str_replace("#", "", $hex);

        $alpha = 1.;

        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            if (strlen($hex) == 8) {
                $a = hexdec(substr($hex, 6, 2));
                $alpha = round(floatval($a) / 255., 2);
            }
        }

        return $this->getColor((255 - $r), (255 - $g), (255 - $b), $alpha);
    }

    /**
     * Returns a new Google_Service_Sheets_Color initialized for grey shades
     *
     * @param int $shade
     * @param int $alpha
     *
     * @return \Google_Service_Sheets_Color
     */
    private function getGrey($shade = 0, $alpha = 1)
    {
        return $this->getColor($shade, $shade, $shade, $alpha);
    }

    /**
     * Prepares the grid styles requests
     *
     * @return array
     */
    private function getStylesRequests()
    {
        //Color is 255 - "actual color component" => 255 - 204 for dark grey
        $grey = $this->getGrey(41);
        $black = $this->getColor();
        $lightgrey = $this->getGrey(136);
        $brown = $this->getColorFromCss("#D35400");
        $green = $this->getColorFromCss("#B7E1CD");

        $leftHeadersLength = count($this->leftHeaders);

        var_dump(array($leftHeadersLength - 2, $leftHeadersLength));

        $requests = array(
            //Merge C4 : D4 cells
            new \Google_Service_Sheets_Request(
                array(
                    'mergeCells' => array(
                        "range"     => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 3,
                            "endRowIndex"      => 4,
                            "startColumnIndex" => $leftHeadersLength - 2,
                            "endColumnIndex"   => $leftHeadersLength,
                        ],
                        "mergeType" => "MERGE_COLUMNS"
                    )
                )
            ),
            //grey for the whole totals column
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 2,
                            "endRowIndex"      => $this->spreadsheetDimension[0],
                            "startColumnIndex" => $leftHeadersLength - 1,
                            "endColumnIndex"   => $leftHeadersLength,
                        ],
                        "cell"   => [
                            "userEnteredFormat" => ["backgroundColor" => $grey]
                        ],
                        "fields" => "userEnteredFormat.backgroundColor"
                    )
                )
            ),
            //grey the quantity line
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => ($this->spreadsheetDimension[2] - 2),
                            "endRowIndex"      => ($this->spreadsheetDimension[2] - 1),
                            "startColumnIndex" => ($leftHeadersLength - 1),
                            "endColumnIndex"   => ($this->spreadsheetDimension[1] - 1),
                        ],
                        "cell"   => [
                            "userEnteredFormat" => ["backgroundColor" => $grey]
                        ],
                        "fields" => "userEnteredFormat.backgroundColor"
                    )
                )
            ),
            // center + brown color + 13px font size on product names
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 0,
                            "endRowIndex"      => 1,
                            "startColumnIndex" => $leftHeadersLength,
                            "endColumnIndex"   => $this->spreadsheetDimension[1],
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "textFormat"          => [
                                    "fontSize"        => 13,
                                    "foregroundColor" => $brown
                                ],
                                "horizontalAlignment" => "CENTER",
                            ]
                        ],
                        "fields" => "userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment"
                    )
                )
            ),
            // Cabin font family, light-grey color, word wrap ... on description
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 1,
                            "endRowIndex"      => 2,
                            "startColumnIndex" => $leftHeadersLength,
                            "endColumnIndex"   => ($this->spreadsheetDimension[1] - 1),
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "textFormat"          => [
                                    "fontFamily"      => "Cabin",
                                    "foregroundColor" => $lightgrey
                                ],
                                "verticalAlignment"   => "TOP",
                                "horizontalAlignment" => "CENTER",
                                "wrapStrategy"        => "WRAP"
                            ]
                        ],
                        "fields" => "userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment,userEnteredFormat.wrapStrategy"
                    )
                )
            ),
            // currency format on prices line
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 2,
                            "endRowIndex"      => 3,
                            "startColumnIndex" => $leftHeadersLength,
                            "endColumnIndex"   => ($this->spreadsheetDimension[1] - 1),
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "numberFormat" => [
                                    "type"    => "CURRENCY",
                                    "pattern" => "# ##0.00 €"
                                ]
                            ]
                        ],
                        "fields" => "userEnteredFormat.numberFormat"
                    )
                )
            ),
            // currency format on total columns
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => ($this->spreadsheetDimension[2] - 1),
                            // -1 because the index is 0 based instead of the spreadsheet grid which starts from 1
                            "endRowIndex"      => $this->spreadsheetDimension[0],
                            "startColumnIndex" => ($leftHeadersLength - 1),
                            "endColumnIndex"   => $leftHeadersLength,
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "numberFormat" => [
                                    "type"    => "CURRENCY",
                                    "pattern" => "# ##0.00 €"
                                ]
                            ]
                        ],
                        "fields" => "userEnteredFormat.numberFormat"
                    )
                )
            ),
            // conditional format rule to put green color each even rows
            new \Google_Service_Sheets_Request(
                array(
                    'addConditionalFormatRule' => array(
                        "rule" => array(
                            "ranges"      => [
                                "sheetId"          => $this->sheetId,
                                "startRowIndex"    => ($this->spreadsheetDimension[2] - 1),
                                "endRowIndex"      => $this->spreadsheetDimension[0],
                                "startColumnIndex" => 1,
                                "endColumnIndex"   => $this->spreadsheetDimension[1],
                            ],
                            "booleanRule" => [
                                "condition" => array(
                                    "type"   => "CUSTOM_FORMULA",
                                    "values" => array("userEnteredValue" => "=ISEVEN(ROW())")
                                ),
                                "format"    => [
                                    "backgroundColor" => $green
                                ]
                            ]
                        )
                    )
                )
            ),
            // border-right black on the 1 column
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 0,
                            "endRowIndex"      => $this->spreadsheetDimension[0],
                            "startColumnIndex" => 0,
                            "endColumnIndex"   => 1,
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "borders" => new \Google_Service_Sheets_Borders(
                                    array(
                                        "right" => new \Google_Service_Sheets_Border(
                                            array("style" => "SOLID", "color" => $black)
                                        )
                                    )
                                )
                            ]
                        ],
                        "fields" => "userEnteredFormat.borders"
                    )
                )
            ),
            // Border right black on the last column
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => 0,
                            "endRowIndex"      => $this->spreadsheetDimension[0],
                            "startColumnIndex" => ($this->spreadsheetDimension[1] - 1),
                            "endColumnIndex"   => ($this->spreadsheetDimension[1]),
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "borders" => new \Google_Service_Sheets_Borders(
                                    array(
                                        "right" => new \Google_Service_Sheets_Border(
                                            array("style" => "SOLID", "color" => $black)
                                        )
                                    )
                                )
                            ]
                        ],
                        "fields" => "userEnteredFormat.borders"
                    )
                )
            ),
            // Border bottom on last row
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => ($this->spreadsheetDimension[0] - 1),
                            "endRowIndex"      => $this->spreadsheetDimension[0],
                            "startColumnIndex" => 1,
                            "endColumnIndex"   => $this->spreadsheetDimension[1],
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "borders" => new \Google_Service_Sheets_Borders(
                                    array(
                                        "bottom" => new \Google_Service_Sheets_Border(
                                            array("style" => "SOLID", "color" => $black)
                                        ),
                                    )
                                )
                            ]
                        ],
                        "fields" => "userEnteredFormat.borders"
                    )
                )
            ),
            // Border bottom & border right on bottom right corner
            new \Google_Service_Sheets_Request(
                array(
                    'repeatCell' => array(
                        "range"  => [
                            "sheetId"          => $this->sheetId,
                            "startRowIndex"    => ($this->spreadsheetDimension[0] - 1),
                            "endRowIndex"      => $this->spreadsheetDimension[0],
                            "startColumnIndex" => ($this->spreadsheetDimension[1] - 1),
                            "endColumnIndex"   => $this->spreadsheetDimension[1],
                        ],
                        "cell"   => [
                            "userEnteredFormat" => [
                                "borders" => new \Google_Service_Sheets_Borders(
                                    array(
                                        "bottom" => new \Google_Service_Sheets_Border(
                                            array("style" => "SOLID", "color" => $black)
                                        ),
                                        "right"  => new \Google_Service_Sheets_Border(
                                            array("style" => "SOLID", "color" => $black)
                                        ),
                                    )
                                )
                            ]
                        ],
                        "fields" => "userEnteredFormat.borders"
                    )
                )
            ),
            // Totals columns width is 55px
            new \Google_Service_Sheets_Request(
                array(
                    'updateDimensionProperties' => array(
                        "range"      => [
                            "sheetId"    => $this->sheetId,
                            "dimension"  => "COLUMNS", // we target columns
                            "startIndex" => ($leftHeadersLength - 1),
                            "endIndex"   => ($leftHeadersLength)
                        ],
                        "properties" => new \Google_Service_Sheets_DimensionProperties(array("pixelSize" => 55)),
                        "fields"     => "pixelSize"
                    )
                )
            ),
            // Products columns get 205px width
            new \Google_Service_Sheets_Request(
                array(
                    'updateDimensionProperties' => array(
                        "range"      => [
                            "sheetId"    => $this->sheetId,
                            "dimension"  => "COLUMNS",
                            "startIndex" => $leftHeadersLength,
                            "endIndex"   => $this->spreadsheetDimension[1]
                        ],
                        "properties" => new \Google_Service_Sheets_DimensionProperties(array("pixelSize" => 205)),
                        "fields"     => "pixelSize"
                    )
                )
            ),
            // Comments column get 350px width
            new \Google_Service_Sheets_Request(
                array(
                    'updateDimensionProperties' => array(
                        "range"      => [
                            "sheetId"    => $this->sheetId,
                            "dimension"  => "COLUMNS",
                            "startIndex" => ($this->spreadsheetDimension[1] - 1),
                            "endIndex"   => ($this->spreadsheetDimension[1])
                        ],
                        "properties" => new \Google_Service_Sheets_DimensionProperties(array("pixelSize" => 350)),
                        "fields"     => "pixelSize"
                    )
                )
            )
        );

        return $requests;
    }

    /**
     * Prepares the protected ranges requests
     *
     * @return array
     */
    private function getProtectedRangesRequests()
    {
        $requests = array(
            new \Google_Service_Sheets_Request(
                array(
                    'addProtectedRange' => array(
                        "protectedRange" => array(
                            "range"       => [
                                "sheetId"          => $this->sheetId,
                                "startRowIndex"    => ($this->spreadsheetDimension[2] - 1),
                                "endRowIndex"      => $this->spreadsheetDimension[0],
                                "startColumnIndex" => 2,
                                "endColumnIndex"   => 4,
                            ],
                            "description" => "Protection sur la colonne des totaux",
                            "warningOnly" => true
                        )
                    )
                )
            ),
            new \Google_Service_Sheets_Request(
                array(
                    'addProtectedRange' => array(
                        "protectedRange" => array(
                            "range"       => [
                                "sheetId"          => $this->sheetId,
                                "startRowIndex"    => 2,
                                "endRowIndex"      => 4,
                                "startColumnIndex" => 1,
                                "endColumnIndex"   => $this->spreadsheetDimension[1],
                            ],
                            "description" => "Protection sur la ligne des totaux et quantités calculés",
                            "editors"     => $this->ggSpreadSheetEditors
                        )
                    )
                )
            ),
            new \Google_Service_Sheets_Request(
                array(
                    'addProtectedRange' => array(
                        "protectedRange" => array(
                            "range"       => [
                                "sheetId"          => $this->sheetId,
                                "startRowIndex"    => 3,
                                "endRowIndex"      => $this->spreadsheetDimension[0],
                                "startColumnIndex" => 0,
                                "endColumnIndex"   => 1,
                            ],
                            "description" => "Protection sur la colonne des règlements",
                            "editors"     => $this->ggSpreadSheetEditors
                        )
                    )
                )
            )
        );

        return $requests;
    }
}