<?php
declare(strict_types=1);

function parse_business_xml_upload(array $file): array
{
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        throw new DomainException('Select an XML file to import.');
    }
    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        throw new DomainException('XML files must not exceed 3 MB.');
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new DomainException('The XML upload did not complete successfully. Try the upload again.');
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new DomainException('The uploaded XML file could not be verified.');
    }
    if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xml') {
        throw new DomainException('Only .xml files can be imported.');
    }
    $size = filesize((string) $file['tmp_name']);
    if ($size === false || $size < 1 || $size > 3 * 1024 * 1024) {
        throw new DomainException('XML files must be between 1 byte and 3 MB.');
    }
    $xml = file_get_contents((string) $file['tmp_name']);
    if ($xml === false) {
        throw new DomainException('The uploaded XML file could not be read.');
    }
    return parse_business_xml($xml, basename((string) $file['name']));
}

function parse_business_xml(string $xml, string $filename = 'import.xml'): array
{
    if ($xml === '' || strlen($xml) > 3 * 1024 * 1024) {
        throw new DomainException('XML files must be between 1 byte and 3 MB.');
    }
    if (preg_match('//u', $xml) !== 1) {
        throw new DomainException('The XML file must use valid UTF-8 encoding.');
    }
    $declaration = preg_replace('/^\xEF\xBB\xBF/', '', $xml);
    if (
        preg_match('/<\?xml\b.*?\bencoding\s*=\s*(["\'])([^"\']+)\1/is', (string) $declaration, $encoding)
        && !in_array(strtoupper($encoding[2]), ['UTF-8', 'UTF8'], true)
    ) {
        throw new DomainException('The XML declaration must specify UTF-8 encoding.');
    }
    if (preg_match('/<!\s*DOCTYPE\b/i', $xml) === 1) {
        throw new DomainException('DOCTYPE declarations and external entities are not permitted.');
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    try {
        $root = simplexml_load_string(
            $xml,
            SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA
        );
        if ($root === false) {
            throw new DomainException('The XML is malformed or uses an unsupported encoding.');
        }
        if ($root->getName() !== 'businessContent' || count($root->attributes()) > 0) {
            throw new DomainException('The XML root must be <businessContent> with no attributes.');
        }
        if (trim((string) $root) !== '') {
            throw new DomainException('The XML root may contain only <item> records and whitespace.');
        }

        $children = $root->children();
        if (count($children) === 0 || count($children) > 500) {
            throw new DomainException('The XML must contain between 1 and 500 <item> records.');
        }
        $records = [];
        $errors = [];
        $seen = [];
        $recordNumber = 0;
        foreach ($children as $item) {
            $recordNumber++;
            $error = null;
            if ($item->getName() !== 'item' || count($item->attributes()) > 0) {
                $errors[] = "Record $recordNumber: only <item> elements without attributes are allowed.";
                continue;
            }
            if (trim((string) $item) !== '') {
                $errors[] = "Record $recordNumber: unexpected text outside the content fields.";
                continue;
            }
            $fields = [];
            foreach ($item->children() as $field) {
                $name = $field->getName();
                if (!in_array($name, ['title', 'text', 'reference_link'], true) || array_key_exists($name, $fields)) {
                    $error = 'record contains an unexpected or duplicate field';
                    break;
                }
                if (count($field->attributes()) > 0 || count($field->children()) > 0) {
                    $error = "$name must contain plain text only";
                    break;
                }
                $fields[$name] = trim((string) $field);
            }
            if ($error === null && (!isset($fields['title']) || $fields['title'] === '')) {
                $error = 'title is required';
            }
            if ($error === null) {
                $titleLength = preg_match_all('/./us', $fields['title']);
                if ($titleLength === false || $titleLength > 150) {
                    $error = 'title must be 150 characters or fewer';
                }
            }
            if ($error === null && (!isset($fields['text']) || $fields['text'] === '')) {
                $error = 'text is required';
            }
            if ($error === null && strlen($fields['text']) > 65535) {
                $error = 'text must be 65,535 bytes or fewer';
            }
            $referenceLink = $fields['reference_link'] ?? '';
            if ($error === null && $referenceLink !== '' && !valid_business_http_url($referenceLink, 500)) {
                $error = 'reference_link must be a valid HTTP or HTTPS URL no longer than 500 bytes';
            }
            if ($error !== null) {
                $errors[] = "Record $recordNumber: $error.";
                continue;
            }

            $referenceLink = $referenceLink === '' ? null : $referenceLink;
            $normalizedText = str_replace(["\r\n", "\r"], "\n", $fields['text']);
            $hash = hash('sha256', trim($fields['title']) . "\0" . $normalizedText . "\0" . ($referenceLink ?? ''));
            if (isset($seen[$hash])) {
                $errors[] = "Record $recordNumber: duplicate content in this XML file.";
                continue;
            }
            $seen[$hash] = true;
            $records[] = [
                'source_record' => $recordNumber,
                'title' => trim($fields['title']),
                'text_content' => $normalizedText,
                'reference_link' => $referenceLink,
                'content_hash' => $hash,
            ];
        }

        return ['filename' => $filename, 'records' => $records, 'errors' => $errors];
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function valid_business_http_url(string $url, int $maxLength): bool
{
    if (strlen($url) > $maxLength || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}
