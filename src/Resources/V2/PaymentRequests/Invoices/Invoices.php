<?php

namespace PayOS\Resources\V2\PaymentRequests\Invoices;

use PayOS\Core\APIResource;
use PayOS\Core\FileDownloadResponse;
use PayOS\Core\ObjectSerializer;
use PayOS\Models\V2\PaymentRequests\Invoices\InvoicesInfo;

/**
 * Invoices Resource
 *
 * Handles invoice retrieval and download operations for payment links
 */
class Invoices extends APIResource
{
    /**
     * Retrieve invoices of a payment link by payment link ID or order code.
     *
     * @param string|int $id paymentLinkId or orderCode
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return InvoicesInfo|array The invoices information
     * @throws \PayOS\Exceptions\APIException
     * @throws \InvalidArgumentException If required parameters are missing
     */
    public function get(string|int $id, ?array $options = null): InvoicesInfo|array
    {
        $requestOptions = array_merge($options ?? [], [
            'signatureOpts' => [
                'response' => 'body',
            ],
        ]);

        $data = $this->_client->get("/v2/payment-requests/{$id}/invoices", $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, InvoicesInfo::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Download an invoice in PDF format by payment link ID or order code.
     *
     * @param string $invoiceId The invoice ID
     * @param string|int $id paymentLinkId or orderCode
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return FileDownloadResponse The invoice file in PDF format
     * @throws \PayOS\Exceptions\APIException
     * @throws \InvalidArgumentException If required parameters are missing
     */
    public function download(
        string $invoiceId,
        string|int $id,
        ?array $options = null
    ): FileDownloadResponse {
        $downloadOptions = array_merge($options ?? [], [
            'method' => \PayOS\Core\HTTPMethod::GET,
        ]);

        return $this->_client->downloadFile(
            "/v2/payment-requests/{$id}/invoices/{$invoiceId}/download",
            $downloadOptions
        );
    }
}
