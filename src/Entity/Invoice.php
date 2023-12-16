<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $client_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $client_company_address = null;

    #[ORM\Column(length: 100)]
    private ?string $invoice_number = null;

    #[ORM\Column(length: 255)]
    private ?string $invoice_service_description = null;

    #[ORM\Column(length: 100)]
    private ?string $invoice_amount_ht = null;

    #[ORM\Column(length: 100)]
    private ?string $invoice_amount_ttc = null;

    #[ORM\Column(length: 100)]
    private ?string $invoice_tax_amount = null;

    #[ORM\Column(length: 255)]
    private ?string $invoice_periode = null;

    #[ORM\Column(length: 255)]
    private ?string $invoice_payment_condition = null;

    #[ORM\Column(length: 255)]
    private ?string $client_company_postal_code = null;

    #[ORM\Column(length: 255)]
    private ?string $client_address_city = null;

    #[ORM\Column(length: 255)]
    private ?string $invoice_date = null;

    #[ORM\Column(length: 255)]
    private ?string $related_invoice_ref = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $total_paid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $payment_status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $invoice_comment = null;

   
    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->company_name;
    }

    public function setCompanyName(?string $company_name): self
    {
        $this->company_name = $company_name;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->client_name;
    }

    public function setClientName(?string $client_name): self
    {
        $this->client_name = $client_name;

        return $this;
    }

    public function getClientCompanyAddress(): ?string
    {
        return $this->client_company_address;
    }

    public function setClientCompanyAddress(?string $client_company_address): self
    {
        $this->client_company_address = $client_company_address;

        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoice_number;
    }

    public function setInvoiceNumber(string $invoice_number): self
    {
        $this->invoice_number = $invoice_number;

        return $this;
    }

    public function getInvoiceServiceDescription(): ?string
    {
        return $this->invoice_service_description;
    }

    public function setInvoiceServiceDescription(string $invoice_service_description): self
    {
        $this->invoice_service_description = $invoice_service_description;

        return $this;
    }

    public function getInvoiceAmountHt(): ?string
    {
        return $this->invoice_amount_ht;
    }

    public function setInvoiceAmountHt(string $invoice_amount_ht): self
    {
        $this->invoice_amount_ht = $invoice_amount_ht;

        return $this;
    }

    public function getInvoiceAmountTtc(): ?string
    {
        return $this->invoice_amount_ttc;
    }

    public function setInvoiceAmountTtc(string $invoice_amount_ttc): self
    {
        $this->invoice_amount_ttc = $invoice_amount_ttc;

        return $this;
    }

    public function getInvoiceTaxAmount(): ?string
    {
        return $this->invoice_tax_amount;
    }

    public function setInvoiceTaxAmount(string $invoice_tax_amount): self
    {
        $this->invoice_tax_amount = $invoice_tax_amount;

        return $this;
    }

    public function getInvoicePeriode(): ?string
    {
        return $this->invoice_periode;
    }

    public function setInvoicePeriode(string $invoice_periode): self
    {
        $this->invoice_periode = $invoice_periode;

        return $this;
    }

    public function getInvoicePaymentCondition(): ?string
    {
        return $this->invoice_payment_condition;
    }

    public function setInvoicePaymentCondition(string $invoice_payment_condition): self
    {
        $this->invoice_payment_condition = $invoice_payment_condition;

        return $this;
    }

    public function getClientCompanyPostalCode(): ?string
    {
        return $this->client_company_postal_code;
    }

    public function setClientCompanyPostalCode(string $client_company_postal_code): self
    {
        $this->client_company_postal_code = $client_company_postal_code;

        return $this;
    }

    public function getClientAddressCity(): ?string
    {
        return $this->client_address_city;
    }

    public function setClientAddressCity(string $client_address_city): self
    {
        $this->client_address_city = $client_address_city;

        return $this;
    }

    public function getInvoiceDate(): ?string
    {
        return $this->invoice_date;
    }

    public function setInvoiceDate(string $invoice_date): self
    {
        $this->invoice_date = $invoice_date;

        return $this;
    }

    public function getRelatedInvoiceRef(): ?string
    {
        return $this->related_invoice_ref;
    }

    public function setRelatedInvoiceRef(string $related_invoice_ref): self
    {
        $this->related_invoice_ref = $related_invoice_ref;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getTotalPaid(): ?string
    {
        return $this->total_paid;
    }

    public function setTotalPaid(?string $total_paid): self
    {
        $this->total_paid = $total_paid;

        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->payment_status;
    }

    public function setPaymentStatus(?string $payment_status): self
    {
        $this->payment_status = $payment_status;

        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function setCreatedAt(?string $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getInvoiceComment(): ?string
    {
        return $this->invoice_comment;
    }

    public function setInvoiceComment(?string $invoice_comment): self
    {
        $this->invoice_comment = $invoice_comment;

        return $this;
    }

}
