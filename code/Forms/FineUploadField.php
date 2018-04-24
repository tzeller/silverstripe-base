<?php

namespace LeKoala\Base\Forms;

use SilverStripe\Forms\FileField;
use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\AssetAdmin\Forms\UploadField;

/**
 * @link https://github.com/FineUploader/php-traditional-server/blob/master/handler.php
 */
class FineUploadField extends UploadField
{
    /**
     * @config
     * @var array
     */
    private static $allowed_actions = [
        'upload', 'deletefile', 'initialfiles'
    ];

    public function Field($properties = array())
    {
        Requirements::css('https://cdnjs.cloudflare.com/ajax/libs/file-uploader/5.15.5/fine-uploader.min.css');

        // For gallery layout
        Requirements::css('https://cdnjs.cloudflare.com/ajax/libs/file-uploader/5.15.5/fine-uploader-gallery.min.css');

        // For rows layout
        // Requirements::css('https://cdnjs.cloudflare.com/ajax/libs/file-uploader/5.15.5/fine-uploader-new.min.css');

        Requirements::javascript('https://cdnjs.cloudflare.com/ajax/libs/file-uploader/5.15.5/fine-uploader.min.js');
        Requirements::javascript('base/javascript/fields/FineUploadField.js');

        return parent::Field($properties);
    }

    public function getSchemaDataDefaults()
    {
        $defaults = parent::getSchemaDataDefaults();
        $deleteLink = $this->Link('deletefile');
        $defaults['data']['deleteFileEndpoint'] = [
            'url' => $deleteLink,
            'method' => 'post',
        ];
        $initialLink = $this->Link('initialfiles');
        $defaults['data']['initialFilesEndpoint'] = [
            'url' => $initialLink,
            'method' => 'post',
        ];
        return $defaults;
    }

    public function getSchemaStateDefaults()
    {
        $state = FormField::getSchemaStateDefaults();
        $state['value'] = $this->Value() ? : ['Files' => []];
        return $state;
    }


    /**
     * Get initial files
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function initialfiles(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }

        $result = [];
        foreach ($this->getItems() as $file) {

            $thumbnail = $file->PreviewLink();

            $result[] = [
                "name" => $file->Title,
                "uuid" => $file->ID,
                "thumbnailUrl" => $thumbnail,
            ];
        }

        return (new HTTPResponse(json_encode($result)))
            ->addHeader('Content-Type', 'application/json');
    }

    /**
     * Creates a single file based on a form-urlencoded upload.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function upload(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }

        // CSRF check
        $token = $this->getForm()->getSecurityToken();
        if (!$token->checkRequest($request)) {
            return $this->httpError(400);
        }

        $tmpFile = $request->postVar('Upload');
        /** @var File $file */
        $file = $this->saveTemporaryFile($tmpFile, $error);

        // Prepare result
        if ($error) {
            $result = [
                'message' => [
                    'type' => 'error',
                    'value' => $error,
                ]
            ];
            $this->getUpload()->clearErrors();
            return (new HTTPResponse(json_encode($result), 400))
                ->addHeader('Content-Type', 'application/json');
        }

        // Return success response
        $result = [
            AssetAdmin::singleton()->getObjectFromData($file)
        ];

        // Don't discard pre-generated client side canvas thumbnail
        if ($result[0]['category'] === 'image') {
            unset($result[0]['thumbnail']);
        }
        $this->getUpload()->clearErrors();
        return (new HTTPResponse(json_encode($result)))
            ->addHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a single file based on a form-urlencoded upload.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function deletefile(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }

        // CSRF check
        $token = $this->getForm()->getSecurityToken();
        if (!$token->checkRequest($request)) {
            return $this->httpError(400);
        }

        $tmpFile = $request->postVar('Upload');
        /** @var File $file */
        $file = $this->saveTemporaryFile($tmpFile, $error);

        // Prepare result
        if ($error) {
            $result = [
                'message' => [
                    'type' => 'error',
                    'value' => $error,
                ]
            ];
            $this->getUpload()->clearErrors();
            return (new HTTPResponse(json_encode($result), 400))
                ->addHeader('Content-Type', 'application/json');
        }

        // Return success response
        $result = [
            AssetAdmin::singleton()->getObjectFromData($file)
        ];

        // Don't discard pre-generated client side canvas thumbnail
        if ($result[0]['category'] === 'image') {
            unset($result[0]['thumbnail']);
        }
        $this->getUpload()->clearErrors();
        return (new HTTPResponse(json_encode($result)))
            ->addHeader('Content-Type', 'application/json');
    }

    public function Type()
    {
        return 'fineupload';
    }
}
