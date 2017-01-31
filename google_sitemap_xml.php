<?php
/**
 * Google XML Sitemap
 * -----------------------------------------------
 * https://github.com/proudcommerce/google_sitemap
 * -----------------------------------------------
 * by DIATOM Internet & Medien GmbH // 27.07.2009
 * by Proud Sourcing GmbH // 19.07.2013
 * by Joachim Barthel  // 25.07.2016
 * -----------------------------------------------
 * / install
 *(1) insert your paths and data below //configuration
 *(2) upload file to your webspace
 *(3) adjust chmod if needed
 *(4) open with your browser
 *(5) open sitemap.xml and check content
 * / transfer to google
 *(1) open www.google.com/webmasters/tools
 *(2) log in with your account
 *(3) choose website
 *(4) "XML-Sitemaps" -> "add Sitemap"
 *(5) specify URL of your sitemap.xml
 * =====================================================================
 */

// init
$mod_cnf                   = [];
$error                     = [];
$xmlInsert                 = [];
$xmlList                   = [];
$xmlList_cat               = [];
$xmlList_cms               = [];
$xmlList_vendor            = [];
$xmlList_manufacturer      = [];
$xmlList_tags              = [];
$xmlList_static            = [];
$xmlList_prod              = [];
$xmlList_prod_vendor       = [];
$xmlList_prod_manufacturer = [];

// Shop-Configuration wrapper
class ShopConfig
{
    /**
     * @var string
     */
    public $dbHost;

    /**
     * @var string
     */
    public $dbName;

    /**
     * @var string
     */
    public $dbUser;

    /**
     * @var string
     */
    public $dbPwd;

    /**
     * @var string
     */
    public $sShopDir;

    public function __construct()
    {
        //$sShopDir = './';   // if stored in shop root
        $sShopDir = '../';  // if stored eg. in /bin folder
        include_once $sShopDir . 'config.inc.php';

        /* append sShopURL with / */
        $this->sShopURL = rtrim($this->sShopURL, '/') . '/';
    }
}

$shopConfig = new ShopConfig();

// configuration database
$mod_cnf['siteurl'] = $shopConfig->sShopURL;    // shop url (with ending slash!)
$mod_cnf['dbhost']  = $shopConfig->dbHost;      // dbhost
$mod_cnf['dbname']  = $shopConfig->dbName;      // dbname
$mod_cnf['dbuser']  = $shopConfig->dbUser;      // dbuser
$mod_cnf['dbpass']  = $shopConfig->dbPwd;       // dbpass

// configuration data
$mod_cnf['exportdir'] = 'export';
$mod_cnf['filepath']  = "{$shopConfig->sShopDir}/{$mod_cnf['exportdir']}/";   // fullpath to sitemaps
$mod_cnf['filename']  = 'sitemap';    // basename for sitemaps
$mod_cnf['offset']    =
    20000;        // how many product-urls in each sitemap? (max. allowed: 50.000 urls (total, with cats and cms) && max. filesize: 10Mb (uncompressed!))
$mod_cnf['language']  = 0;            // shop language id
$mod_cnf['expired']   = true;         // true for using also oxseo.oxexpired = 1 (normally only oxseo.oxexpired = 0)

// configuration export
$mod_cnf['export_categories']   = true;                     // export categories?
$mod_cnf['export_products']     = true;                     // export products?
$mod_cnf['export_products_ma']  = true;                     // export manufacturer products?
$mod_cnf['export_products_ve']  = true;                     // export vendor products?
$mod_cnf['export_cms']          = true;                     // export cms pages?
$mod_cnf['export_vendor']       = true;                     // export vendors?
$mod_cnf['export_manufacturer'] = true;                     // export manufacturers?
$mod_cnf['export_tags']         = true;                     // export tags?
$mod_cnf['export_static']       = true;                     // export static seo urls?

/* ----------------- DO NOT EDIT ANYTHING BEHIND THIS LINE ----------------- */

// which run?: script calls with '-c [n]'
// first run (without params) -> call #1
$pcall = 1;
if (isset($_SERVER['argc'][1])) {
    if ("-c" == $_SERVER['argv'][1]) {
        $pcall = $_SERVER['argv'][2];
        if (!preg_match("/\d+/", $pcall)) {
            die("Illegal call.\n");
        }
    }
}

$mod_cnf['dbport'] = "";

if (false !== strpos($mod_cnf['dbhost'], ':')) {
    $aTmp              = explode(":", $mod_cnf['dbhost']);
    $mod_cnf['dbhost'] = $aTmp[0];
    $mod_cnf['dbport'] = $aTmp[1];
}

$dsn = "mysql:host={$mod_cnf['dbhost']};dbname={$mod_cnf['dbname']}";
if (!empty($mod_cnf['dbport'])) {
    $dsn .= ";port={$mod_cnf['dbport']}";
}

// db connection
$dbh = new PDO($dsn, $mod_cnf['dbuser'], $mod_cnf['dbpass']);

//** get number of needed script-calls, based on active items with valid seo-url. cms and categories will be added to first sitemap automatically.
$cntCalls = ceil(getCountScriptCalls() / $mod_cnf['offset']);

// store cms- and category-data only at first call, further calls are products only
if (1 == $pcall) {
    // get cms data from shop - only at first script-run! (-c 1)
    if ($mod_cnf['export_cms']) {
        $xmlList_cms = getCmsSite();
    }

    // get vendor data from shop - only at first script-run! (-c 1)
    if ($mod_cnf['export_vendor']) {
        $xmlList_vendor = getVendors();
    }

    // get manufacturer data from shop - only at first script-run! (-c 1)
    if ($mod_cnf['export_manufacturer']) {
        $xmlList_manufacturer = getManufacturers();
    }

    // get manufacturer data from shop
    if ($mod_cnf['export_tags']) {
        $xmlList_tags = getTags();
    }

    // get static seo data from shop
    if ($mod_cnf['export_static']) {
        $xmlList_static = getStaticUrls();
    }

    // get all categories
    if ($mod_cnf['export_categories']) {
        $xmlList_cat = getCategories();
    }

    // get vendor products
    if ($mod_cnf['export_products_ve']) {
        $xmlList_prod_vendor = getProductsVendor();
    }

    // get manufacturer products
    if ($mod_cnf['export_products_ma']) {
        $xmlList_prod_manufacturer = getProductsManufacturer();
    }
}

// get products (with offset)
if ($mod_cnf['export_products']) {
    $xmlList_prod = getProducts($pcall);
}

$dbh = null;

// build xml-data and output
$xmlList = array_merge(
    $xmlList_prod,
    $xmlList_prod_vendor,
    $xmlList_prod_manufacturer,
    $xmlList_cat,
    $xmlList_cms,
    $xmlList_vendor,
    $xmlList_manufacturer,
    $xmlList_tags,
    $xmlList_static
);

// create sitemap
$sitemapdata = createSitemap($xmlList);
$smfile      = createXmlFile($sitemapdata);

// compress sitemap
#compressSitemapFile($smfile);

// create global sitemaps-index-file (watch sitemaps.org for more infos..)
createSitemapIndex();

//** RECALL SCRIPT
if ($pcall < $cntCalls) {
    // memory seems to hold list-array-values, maybe depends on local environment
    unset($xmlList, $xmlList_cat, $xmlList_cms, $xmlList_vendor, $xmlList_manufacturer, $xmlList_tags, $xmlList_prod, $xmlList_prod_manufacturer, $xmlList_prod_vendor, $xmlList_static);

    // call itself
    $exec = 'php ' . __FILE__ . ' -c ' . ($pcall + 1);
    //echo "\n".$exec."\n"; //debug
    system($exec);
    exit(0);
}

//** exit all
//echo "\nready.\n";    // debug
exit(0);

// ** FUNCTIONS

/** get all active and visible categories from database
 *
 * @return array
 */
function getCategories()
{
    global $mod_cnf;
    global $dbh;

    $list    = [];
    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT 
                seo.oxseourl
            FROM
                oxcategories as oxcats
            LEFT JOIN
                oxseo as seo ON (oxcats.oxid=seo.oxobjectid)
            WHERE
                oxcats.oxactive = 1 AND
                oxcats.oxhidden = 0 AND
                seo.oxtype='oxcategory' AND
                seo.oxstdurl NOT LIKE ('%pgNr=%') AND
                {$expired}
                seo.oxlang = {$mod_cnf['language']}
            GROUP BY
                oxcats.oxid;";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '1.0',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'weekly',
        ];
    }

    return $list;
}

/** get active cms content from database
 *
 * @return array
 */
function getCmsSite()
{
    global $mod_cnf;
    global $dbh;

    $list    = [];
    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT
                seo.oxseourl
            FROM
                oxcontents as content
            LEFT JOIN
                oxseo as seo ON (content.oxid=seo.oxobjectid)
            WHERE
                content.oxactive = 1 AND
                content.oxfolder = ''
                AND seo.oxseourl <> ''
                AND seo.oxseourl NOT LIKE ('%META%')
                {$expired}
                AND seo.oxlang = {$mod_cnf['language']}
            GROUP BY
                content.oxid;";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.6',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'weekly',
        ];
    }

    return $list;
}

/** get active vendors from database
 *
 * @return array
 */
function getVendors()
{
    global $mod_cnf;
    global $dbh;

    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';
    $list    = [];

    $sql = "SELECT
                seo.oxseourl
            FROM
                oxvendor as vendor
            LEFT JOIN
                oxseo as seo ON (vendor.oxid=seo.oxobjectid)
            WHERE
                vendor.oxactive = 1 AND
                seo.oxseourl <> '' AND
                seo.oxtype='oxvendor' AND
                {$expired}
                seo.oxlang = {$mod_cnf['language']}
            GROUP BY
                vendor.oxid;";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.7',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'weekly',
        ];
    }

    return $list;
}

/** get active manufacturers from database
 *
 * @return array
 */
function getManufacturers()
{
    global $mod_cnf;
    global $dbh;
    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';
    $list    = [];

    $sql = "SELECT
                seo.oxseourl
            FROM
                oxmanufacturers as manufacturer
            LEFT JOIN
                oxseo as seo ON (manufacturer.oxid=seo.oxobjectid)
            WHERE
                manufacturer.oxactive = 1 AND
                seo.oxseourl <> '' AND
                seo.oxtype='oxmanufacturer' AND
                {$expired}
                seo.oxlang = {$mod_cnf['language']}
            GROUP BY
                manufacturer.oxid;";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.7',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'weekly',
        ];
    }

    return $list;
}

/** get active manufacturers from database
 *
 * @return array
 */
function getTags()
{
    global $mod_cnf;
    global $dbh;

    $list    = [];
    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT
                seo.oxseourl
            FROM
                oxseo seo
            WHERE
                seo.oxseourl <> '' AND
                seo.oxstdurl LIKE '%=tag%' AND
                seo.oxtype='dynamic' AND
                {$expired}
                seo.oxlang = " . $mod_cnf['language'];

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.8',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'weekly',
        ];
    }

    return $list;
}

/** get static seo urls from database
 *
 * @return array
 */
function getStaticUrls()
{
    global $mod_cnf;
    global $dbh;

    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';
    $list    = [];

    $sql = "SELECT
                seo.oxseourl
            FROM
                oxseo seo
            WHERE
                seo.oxseourl <> '' AND
                seo.oxtype='static' AND
                {$expired}
                seo.oxlang = " . $mod_cnf['language'];

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.5',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'weekly',
        ];
    }

    return $list;
}

/**
 * get active products from database with offset
 *
 * @param int $limit
 *
 * @return array
 */
function getProducts($limit)
{
    global $mod_cnf;
    global $dbh;
    $list = [];

    // calculate offset
    $start = $mod_cnf['offset'];
    if (1 == $limit) {
        $end = 0;
    } else {
        $end = (($limit - 1) * $mod_cnf['offset']) - 1;
    }

    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT
                oxart.oxtimestamp,
                seo.oxseourl
            FROM
                oxarticles as oxart
            LEFT JOIN oxobject2category as oxobj2cat
                ON (oxobj2cat.oxobjectid = oxart.oxid)
            LEFT JOIN oxcategories as oxcat
                ON (oxcat.oxid = oxobj2cat.oxcatnid)
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                oxcat.oxactive = 1 AND
                oxcat.oxhidden = 0 AND
                seo.oxlang = {$mod_cnf['language']} AND
                seo.oxtype='oxarticle' AND
                {$expired}
                seo.oxstdurl LIKE ('%cnid=%')
            GROUP BY
                oxart.oxid
            LIMIT " . $start . " OFFSET " . $end . ";";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '1.0',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'daily',
        ];
    }

    return $list;
}

/** get manufacturer product urls from database
 *
 * @return array
 */
function getProductsManufacturer()
{
    global $mod_cnf;
    global $dbh;

    $list    = [];
    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT
                oxart.oxtimestamp,
                seo.oxseourl
            FROM
                oxarticles as oxart
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                seo.oxlang = {$mod_cnf['language']} AND
                seo.oxtype='oxarticle' AND
                {$expired}
                seo.oxstdurl LIKE ('%mnid=%')
            GROUP BY
                oxart.oxid";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.9',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'daily',
        ];
    }

    return $list;
}

/** get vendor product urls from database
 *
 * @return array
 */
function getProductsVendor()
{
    global $mod_cnf;
    global $dbh;

    $list    = [];
    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT
                oxart.oxtimestamp,
                seo.oxseourl
            FROM
                oxarticles as oxart
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                seo.oxlang = {$mod_cnf['language']} AND
                seo.oxtype='oxarticle' AND
                {$expired}
                seo.oxstdurl LIKE ('%cnid=v%')
            GROUP BY
                oxart.oxid";

    foreach ($dbh->query($sql) as $row) {
        $list[] = [
            'loc'        => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'   => '0.9',
            'lastmod'    => date(DateTime::ATOM),
            'changefreq' => 'daily',
        ];
    }

    return $list;
}

/** get total number of 'seo-active' products in shop
 *
 * @return integer
 */
function getCountScriptCalls()
{
    global $mod_cnf;
    global $dbh;

    $expired = $mod_cnf['expired'] ? '' : 'seo.oxexpired = 0 AND ';

    $sql = "SELECT
                COUNT(DISTINCT oxart.oxid)
            FROM
                oxarticles as oxart
            LEFT JOIN oxobject2category as oxobj2cat
                ON (oxobj2cat.oxobjectid = oxart.oxid)
            LEFT JOIN oxcategories as oxcat
                ON (oxcat.oxid = oxobj2cat.oxcatnid)
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                oxcat.oxactive = 1 AND
                oxcat.oxhidden = 0 AND
                {$expired}
                seo.oxlang = {$mod_cnf['language']} AND
                seo.oxtype='oxarticle'";

    $rowCount = $dbh->query($sql)->fetchColumn();

    return $rowCount;
}

/**
 * creates xml data / sitemap-content
 *
 * @param array $data
 *
 * @return array
 */
function createSitemap($data)
{
    $mapdata[] =
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
        "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";

    foreach ($data as $key => $val) {
        $mapdata[] =
            "  <url>\n" .
            "    <loc>{$val['loc']}</loc>\n" .
            "    <priority>{$val['priority']}</priority>\n" .
            "    <lastmod>{$val['lastmod']}</lastmod>\n" .
            "    <changefreq>{$val['changefreq']}</changefreq>\n" .
            "  </url>";
    }

    $mapdata[] = '</urlset>';
    // print sitemap data
    // print_r($mapdata);
    return $mapdata;
}

/**
 * stores xml-file to filesystem
 *
 * @param string $smdata
 *
 * @return string
 */
function createXmlFile($smdata)
{
    global $mod_cnf, $pcall;
    $fname = $mod_cnf['filepath'] . $mod_cnf['filename'] . $pcall . ".xml";
    $fp    = fopen($fname, "w+");
    fwrite($fp, implode("\n", $smdata));
    fclose($fp);

    return $fname;
}

/**
 * compress sitemap-file: new file is sitemap.gz
 *
 * @param string $fname
 *
 * @return void
 */
function compressSitemapFile($fname)
{
    if (file_exists($fname)) {
        system("gzip -q -9 " . $fname);
    }

    return;
}

/**
 * append new sitemap to sitemap index
 *
 * @return void
 */
function createSitemapIndex()
{
    global $pcall, $mod_cnf;
    $sitemaps = [];

    // build xml-content
    $smindex =
        '<?xml version="1.0" encoding="UTF-8"?>' .
        "\n" .
        '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    for ($i = 1; $i <= $pcall; $i++) {
        $loc        =
            '<loc>' . $mod_cnf['siteurl'] . $mod_cnf['exportdir'] . "/" . $mod_cnf['filename'] . $i . '.xml</loc>';
        $last       = '<lastmod>' . date(DateTime::ATOM) . '</lastmod>';
        $sitemaps[] = '<sitemap>' . $loc . $last . '</sitemap>';
    }
    $maps = $smindex . "\n" . implode("\n", $sitemaps);

    $sitemapindex = $maps . "\n</sitemapindex>";

    // write to file
    file_put_contents($mod_cnf['filename'] . '.xml', $sitemapindex);

    return;
}
