<?php
/**
 * Script to automatically retrieve files from the Wayback Machine and update
 * YTIMG references to work locally.
 * 
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 */

const WAYBACK_TEMPLATE = "https://web.archive.org/web/20100402214441id_/";
const YTIMG_TARGET = "/s/";
const YTIMG_LOCALIZATION_TARGET_FILE_EXTS = ["html", "js", "css"];

// lazy glob matching ;-;
$files = glob(getcwd() . "/{*,*/,*/*/,*/*/*/,*/*/*/*/}*", GLOB_BRACE);

/**
 * Print a line to the console.
 */
function p(string $s): void
{
    echo $s . "\n";
}

/**
 * File RAII wrapper
 */
class File
{
    /**
     * @var resource
     */
    public $handle;

    private string $contents;

    public function __construct(string $path, string $mode)
    {
        $this->handle = fopen($path, $mode);
    }

    public function __destruct()
    {
        fclose($this->handle);
    }

    public function getContents(): string
    {
        if (isset($this->contents))
        {
            return $this->contents;
        }

        $this->contents = "";

        while (($buffer = fgets($this->handle, 4096)) !== false)
        {
            $this->contents .= $buffer;
        }

        return $this->contents;
    }
}

function getFileExtension(string $fileName): string
{
    $ext = explode(".", $fileName);

    if (!$ext)
    {
        return "";
    }

    $ext = $ext[count($ext) - 1];

    return $ext;
}

/**
 * Resolve the local storage path of a YTIMG link from a URL.
 * 
 * https://s.ytimg.com/yt/jsbin/www-example-vfl123456.js
 *     ->
 * <path to cwd>/s/yt/jsbin/www-example-vfl123456.js
 */
function resolveLocalYtimgPath(string $url): string
{
    return getcwd() . resolveLocalYtimgUrl($url);
}

/**
 * Resolve the local URL of a YTIMG link from a canonical URL.
 * 
 * https://s.ytimg.com/yt/jsbin/www-example-vfl123456.js
 *     ->
 * /s/yt/jsbin/www-example-vfl123456.js
 */
function resolveLocalYtimgUrl(string $url): string
{
    $url = normalizePath($url);

    $path = explode("s.ytimg.com/", $url)[1];

    return YTIMG_TARGET . "$path";
}

function doesLocalCopyExist(string $url): bool
{
    return file_exists(resolveLocalYtimgPath($url));
}

/**
 * Un-Windows a path (convert \ to /)
 */
function unwindows(string $path): string
{
    return str_replace("\\", "/", $path);
}

/**
 * Normalizes a path for proper parsing.
 */
function normalizePath(string $path): string
{
    return unwindows(str_replace("\/", "/", $path));
}

/**
 * Get the containing folder of a file path.
 */
function getFolder(string $path): string
{
    // Convert the path to account for Windows separation.
    $path = unwindows($path);

    // Split the path by the separator
    $root = explode("/", $path);

    // Remove the last item (the filename)
    array_splice($root, count($root) - 1, 1);

    // Rejoin
    $root = implode("/", $root);

    return $root;
}

/**
 * Downloads a local copy of a YTIMG static resource from the Wayback Machine.
 * 
 * @return bool True on success, false on failure
 */
function downloadLocalCopy(string $url): bool
{
    $requestUri = WAYBACK_TEMPLATE . $url;

    p("Downloading file: $requestUri");

    $fileContents = file_get_contents($requestUri);

    if (false === $fileContents)
    {
        p("Request to URI \"$requestUri\" failed.");
        return false;
    }

    $localFilePath = resolveLocalYtimgPath($url);

    // If the folders leading up to the path don't exist, then we can't open the
    // file for writing. We must ensure that those exist first:
    $folderPath = getFolder($localFilePath);

    if (!is_dir($folderPath))
    {
        mkdir($folderPath, 0777, true);
    }

    p("Request succeeded. Saving to $localFilePath");

    $file = new File($localFilePath, "w");

    if (false === $file->handle)
    {
        p("Failed to open file " . $localFilePath);
        return false;
    }

    $fr = fwrite($file->handle, $fileContents);

    if (false === $fr)
    {
        p("Failed to write to file" . $localFilePath);
        return false;
    }

    return true;
}

/**
 * Localization file name stack.
 * 
 * This is to ensure that we don't ever end up in a infinite loop when dealing
 * with circular references.
 */
$g_localizationStack = [];

const LYTI_DEFER_RECURSION = 0;
const LYTI_SUCCESS = 1;

/**
 * Localize a ytimg path (i.e. convert it to use local URLs)
 * 
 * This means that all URLs including s.ytimg.com/ will be converted to use a
 * local URL format: /s/
 * 
 * @return int  One of the LYTI_ constants
 */
function localizeYtimg(string $fileName): int
{
    global $g_localizationStack;
    global $g_nextStack;

    if (in_array($fileName, $g_localizationStack))
    {
        // Skip because we don't wanna infinite loop.
        $g_nextStack[] = $fileName;
        return LYTI_DEFER_RECURSION;
    }

    // We have to remember our own position in the localization stack, since we
    // can be called up the call stack and thus have the latest entry be updated
    // without informing us of the change.
    $ownStackPosition = addToLocStackUnique($fileName);

    p("Localizing ytimg URLs for file: $fileName");

    $file = new File($fileName, "r+");
    $contents = $file->getContents();

    // Regex backslash forward slash
    $bsfs = "(?:\/|" . "\\\\" . "\/" . ")";

    if (
        preg_match_all(
            "/(?:https:|http:)?{$bsfs}{$bsfs}s\.ytimg\.com{$bsfs}.+?\.\w+/", 
            $contents, $matchSets
        ) === false
    )
    {
        p("No s.ytimg.com links in $file");
    }

    $uniqueMatches = [];

    foreach ($matchSets as $matchSet)
    {
        foreach ($matchSet as $match)
        {
            if (!in_array($match, $uniqueMatches))
            {
                $uniqueMatches[] = $match;
            }
        }
    }

    foreach ($uniqueMatches as $match)
    {
        p("Found VFL url: $match");

        if (doesLocalCopyExist($match))
        {
            // If we have a local copy of the file already, then we'll just try
            // to modify it up the call stack.
            $localPath = resolveLocalYtimgPath($match);

            if (LYTI_DEFER_RECURSION == localizeYtimg($localPath))
            {
                // If we're deferring because of detected recursion, then we
                // have to add ourselves to the next localization stack as well.
                // Note that the above call will already add them before us, so
                // we only have to bother with ourself.
                addToLocStackUnique($fileName);
            }
        }
        else
        {
            // If we're downloading a remote file, then I'll just be lazy and
            // add the current back back to the queue to recheck. If we don't
            // have any matches, then we'll just fail anyways.
            addToLocStackUnique($fileName);

            if (!downloadLocalCopy($match))
            {
                p("It's over :pensive:");
            }
        }

        // We want to prenormalize the path so that, during this replacement
        // procedure, we will only replace all normalized versions of it with
        // normalized versions. If we are escaped, then we want to match the
        // escaping, so this will have no effect on those cases, which is the
        // desired effect.
        $normalizedYtimgPath = normalizePath($match);

        // We want to replace all matches with the local URL, so we'll just
        // indiscriminately replace all references:
        $localUrl = resolveLocalYtimgUrl($normalizedYtimgPath);

        $contents = str_replace($normalizedYtimgPath, $localUrl, $contents);

        if (false !== strpos($match, "\/"))
        {
            $escapedLocalPath = str_replace("/", "\/", $localUrl);

            $contents = str_replace($match, $escapedLocalPath, $contents);
        }
    }

    // Write out the changes we made to the file contents:
    ftruncate($file->handle, 0);
    fseek($file->handle, 0);
    fwrite($file->handle, $contents);

    p("Updated file $fileName");

    // Remove ourself from the stack.
    array_splice($g_localizationStack, $ownStackPosition, 1);

    return LYTI_SUCCESS;
}

/*
 * The stack system used from here exists for deferrence. It allows me to more
 * easily cover things like circular references and remotely downloaded files.
 * 
 * Resolving circular references requires both referencers to be updated
 * independently of each other until both are resolved (this is the lazy way,
 * not the efficient way -- it will open the file a billion different times ;-;)
 * 
 * Remotely downloaded files update the file stack, so we'll need to "add" them
 * to the stack to be updated later. This is done mostly to deal with the
 * aforementioned circular reference resolution system.
 * 
 * The implementation details don't matter because it still works logically the
 * same no matter how you do it, but basically I wipe the entire array and then
 * repush entries if they should be queued for a recheck.
 */

/**
 * Add a value to the localization stack only if it doesn't already exist there.
 * 
 * @return int Index of the new item in the localization stack, or -1 upon
 *             failure.
 */
function addToLocStackUnique(string $filePath): int
{
    global $g_localizationStack;

    if (!in_array($filePath, $g_localizationStack))
    {
        // count() is one-indexed, so its result will always be equivalent to
        // the last item index plus 1.
        $newIndex = count($g_localizationStack);

        $g_localizationStack[] = $filePath;

        return $newIndex;
    }

    return -1;
}

$g_nextStack = $files; // Copied because $files is an array

while (!empty($g_nextStack))
{
    $currentStack = $g_nextStack;
    $g_nextStack = [];

    foreach ($currentStack as $file)
    {
        $ext = getFileExtension($file);

        if (in_array($ext, YTIMG_LOCALIZATION_TARGET_FILE_EXTS))
        {
            localizeYtimg($file);
        }
    }
}