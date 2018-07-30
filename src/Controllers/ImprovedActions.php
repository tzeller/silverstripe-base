<?php
namespace LeKoala\Base\Controllers;

use ReflectionMethod;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use LeKoala\Base\Helpers\ClassHelper;
use LeKoala\Base\Subsite\SubsiteHelper;
use SilverStripe\ORM\ValidationException;

trait ImprovedActions
{
    /**
     * Returns the instance of the requested record for this request
     * Permissions checks are up to you
     *
     * @return DataObject
     */
    public function getRequestedRecord()
    {
        $request = $this->getRequest();

        // Look first in headers
        $class = $request->getHeader('X-RecordClassName');
        if (!$class) {
            $class = $request->requestVar('_RecordClassName');
        }
        $ID = $request->param('ID');
        if (!$class) {
            // Help our fellow developpers
            if ($ID == 'field') {
                throw new ValidationException("Attempt to post on a FormField often result in loosing request params. No record class could be found");
            }
            throw new ValidationException("No class");
        }
        if (!ClassHelper::isValidDataObject($class)) {
            throw new ValidationException("$class is not valid");
        }
        $id = $request->getHeader('X-RecordID');
        if (!$id) {
            $id = (int)$request->requestVar('_RecordID');
        }
        return DataObject::get_by_id($class, $id);
    }


    /**
     * Override default mechanisms for ease of use
     *
     * @link https://docs.silverstripe.org/en/4/developer_guides/controllers/access_control/
     * @param string $action
     * @return boolean
     */
    public function checkAccessAction($action)
    {
        // Whitelist early on to avoid running unecessary code
        if ($action == 'index') {
            return true;
        }
        $isAllowed = $this->isActionWithRequest($action);
        if (!$isAllowed) {
            $isAllowed = parent::checkAccessAction($action);
        }
        if (!$isAllowed) {
            $this->getLogger()->info("$action is not allowed");
        }
        return $isAllowed;
    }

    /**
     * Checks if a given action use a request as first parameter
     *
     * For forms, declare HTTPRequest $request = null because $request is not set
     * when called from the template
     *
     * @param string $action
     * @return boolean
     */
    protected function isActionWithRequest($action)
    {
        if ($this->owner->hasMethod($action)) {
            $refl = new ReflectionMethod($this, $action);
            $params = $refl->getParameters();
            // Everything that gets a request as a parameter is a valid action
            if ($params && $params[0]->getName() == 'request') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    public function hasAction($action)
    {
        $result = parent::hasAction($action);
        if (!$result) {
            $result = $this->isActionWithRequest($action);
        }
        return $result;
    }

    /**
     * Controller's default action handler.  It will call the method named in "$Action", if that method
     * exists. If "$Action" isn't given, it will use "index" as a default.
     *
     * @param HTTPRequest $request
     * @param string $action
     *
     * @return DBHTMLText|HTTPResponse
     */
    protected function handleAction($request, $action)
    {
        try {
            $result = parent::handleAction($request, $action);
        } catch (ValidationException $ex) {
            $caller = $ex->getTrace();
            $callerFile = $caller[0]['file'] ?? 'unknonwn';
            $callerLine = $caller[0]['line'] ?? 0;
            $this->getLogger()->debug($ex->getMessage() . ' in ' . basename($callerFile) . ':' . $callerLine);

            if (Director::is_ajax()) {
                return $this->applicationResponse($ex->getMessage(), [], [
                    'code' => $ex->getCode(),
                ], false);
            } else {
                return $this->error($ex->getMessage());
            }
        }
        return $result;
    }
}