<?php

class ApiUpdateRiskiData extends ApiBase {
    public function execute() {
        // Get params (e.g., a JSON string of updates)
        $params = $this->extractRequestParams();
        $updates = json_decode($params['updates'], true) ?: [];

        // Get session and update
        $session = $this->getRequest()->getSession();
        $pairs = $session->get('riskiData', []);
        $pairs = array_merge($pairs, $updates);  // Or handle deletes/overwrites as needed
        $session->set('riskiData', $pairs);

        // Return success/response
        $result = $this->getResult();
        $result->addValue(null, 'success', true);
    }

    public function getAllowedParams() {
        return [
            'updates' => [
                ApiBase::PARAM_TYPE => 'string',
                ApiBase::PARAM_REQUIRED => true,
            ],
        ];
    }

    public function needsToken() {
        return 'csrf';  // Require CSRF token for write ops
    }

    public function isWriteMode() {
        return true;  // Marks as mutating (uses primary DB if needed)
    }

    public function getExamplesMessages() {
        return [
            'action=updateriskidata&updates={"key1":"val1"}&token=123456'
                => 'apihelp-updateriskidata-example',
        ];
    }
}
