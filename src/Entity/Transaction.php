<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $invoice_number = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $transaction_amount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transaction_payment_method = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mollie_customer_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mollie_payment_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transaction_description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mollie_payment_status = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTransactionAmount(): ?string
    {
        return $this->transaction_amount;
    }

    public function setTransactionAmount(string $transaction_amount): self
    {
        $this->transaction_amount = $transaction_amount;

        return $this;
    }

    public function getTransactionPaymentMethod(): ?string
    {
        return $this->transaction_payment_method;
    }

    public function setTransactionPaymentMethod(?string $transaction_payment_method): self
    {
        $this->transaction_payment_method = $transaction_payment_method;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getMollieCustomerId(): ?string
    {
        return $this->mollie_customer_id;
    }

    public function setMollieCustomerId(?string $mollie_customer_id): self
    {
        $this->mollie_customer_id = $mollie_customer_id;

        return $this;
    }

    public function getMolliePaymentId(): ?string
    {
        return $this->mollie_payment_id;
    }

    public function setMolliePaymentId(?string $mollie_payment_id): self
    {
        $this->mollie_payment_id = $mollie_payment_id;

        return $this;
    }

    public function getTransactionDescription(): ?string
    {
        return $this->transaction_description;
    }

    public function setTransactionDescription(?string $transaction_description): self
    {
        $this->transaction_description = $transaction_description;

        return $this;
    }

    public function getMolliePaymentStatus(): ?string
    {
        return $this->mollie_payment_status;
    }

    public function setMolliePaymentStatus(?string $mollie_payment_status): static
    {
        $this->mollie_payment_status = $mollie_payment_status;

        return $this;
    }
}
