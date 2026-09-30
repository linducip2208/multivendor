<?php

declare(strict_types=1);

namespace App\Shipping;

/**
 * Kontrak provider pengiriman / Shipping provider contract.
 *
 * Semua provider (DHL, FedEx, UPS, USPS, lokal) wajib mendeklarasikan
 * kapabilitasnya secara jujur dan TIDAK PERNAH memanggil API live
 * tanpa kredensial. Tanpa kredensial -> hasil deterministik lokal
 * (stub tarif/pelacakan) sehingga aman dipakai di test.
 */
interface ShippingProviderInterface
{
    public function getCode(): string;

    public function getName(): string;

    /** Kemampuan jujur: negara, layanan, batas berat, fitur. */
    public function capabilities(): array;

    public function supportsCountry(string $country): bool;

    public function supportsService(string $service): bool;

    /**
     * Estimasi tarif. Tanpa kredensial -> stub deterministik lokal.
     *
     * @param array{origin:string,destination:string,weight_kg:float,service?:string} $shipment
     * @return array{provider:string,service:string,amount:float,currency:string,eta_days:int,raw:array}
     */
    public function quote(array $shipment): array;

    /**
     * Buat label pengiriman.
     *
     * @return array{tracking_number:string,label_url:?string,status:string,raw:array}
     */
    public function createLabel(array $shipment): array;

    /** Lacak kiriman. @return array{tracking_number:string,status:string,history:array,raw:array} */
    public function track(string $trackingNumber): array;

    /** Minta penjemputan. @return array{pickup_id:string,status:string,raw:array} */
    public function requestPickup(array $shipment): array;

    /** Batalkan kiriman/label. @return array{tracking_number:string,status:string,raw:array} */
    public function cancel(string $trackingNumber): array;
}
