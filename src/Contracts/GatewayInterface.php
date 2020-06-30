<?php


namespace CoverCMS\Pay\Contracts;


use CoverCMS\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface GatewayInterface
 * @package CoverCMS\Pay\Contracts
 */
interface GatewayInterface
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Collection|Response
     */
    public function pay($endpoint, array $payload);
}