<?php

namespace App\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Invoice;
use App\Entity\MollieUser;
use App\Entity\Transaction;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Mollie\Api\MollieApiClient;

class MollieApisController extends AbstractController {

    private $mollieApiKey;

    public function __construct( $mollieApiKey, EntityManagerInterface $entityManager ) {
        $this->mollieApiKey = $mollieApiKey;
        $this->entityManager = $entityManager;

    }

    #[ Route( '/api/mollie_users', name: 'mollieUsersRetreive' ) ]

    public function index( Request $request ): JsonResponse {

        $mollieUsers = $this->entityManager->getRepository( MollieUser::class )->findAll();
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $page = 1;
        $customersArray = [];

        $customers = $mollie->customers->page();

        $mollieUsersJsonArray = [];
        foreach ( $customers as $key => $value ) {
            array_push( $mollieUsersJsonArray, $value );
        }

        return new JsonResponse( [
            'mollieUsersCount'=>count( $mollieUsersJsonArray ),

            'status' => 'success',
            'customers' => $customers,
            'mollieUsers'=>$mollieUsersJsonArray,
        ] );

    }

    #[ Route( '/api/mollie_user_create', name: 'mollieUserCreate' ) ]

    public function createUser( Request $request ): JsonResponse {

        $name = $request->get( 'name' );

        $email = $request->get( 'email' );
        $locale = $request->get( 'locale' );
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $existingMollieUser = '';
        try {
            $customers = $mollie->customers->page();
            foreach ( $customers as $customer ) {
                if ( $customer->email === $email ) {
                    $existingMollieUser = $customer;
                    break;
                }
            }
        } catch ( \Mollie\Api\Exceptions\ApiException $e ) {
            return new JsonResponse( [
                'status' => 'error',
                '$message' => $$e
            ] );
        }

        if ( $existingMollieUser ) {

            return new JsonResponse( [
                'status' => 'exists already',
                '$customer' => $existingMollieUser
            ] );
        } else {
            $newMollieUser = new MollieUser();
            $newMollieUser->setName( $name );
            $newMollieUser->setEmail( $email );
            $newMollieUser->setLocale( $locale );
            $newMollieUser->setMollieId( $customer->id );

            $this->entityManager->persist( $newMollieUser );
            $this->entityManager->flush();

            $customer = $mollie->customers->create( [
                'name' => $name,
                'email' => $email,
                'locale' => $locale,
            ] );

            return new JsonResponse( [
                'status' => 'success',
                'message'=> 'User created successfully',
                '$customer' => $customer
            ] );
        }

    }

    #[ Route( '/api/mollie_user_update', name: 'mollieUserUpdate' ) ]

    public function updateMollieUser( Request $request ): JsonResponse {

        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $name = $request->get( 'name' );
        $email = $request->get( 'email' );
        $locale = $request->get( 'locale' );
        $customerId = $request->get( 'customerId' );

        $customer = $mollie->customers->get( $customerId );
        $customer->name = $name;
        $customer->email = $email;
        $customer->locale = $locale;
        $mollie->customers->update( $customerId, [
            'name' => $customer->name,
            'email' => $customer->email,
            'locale' => $customer->locale,
        ] );

        $existingMollieUser = $this->entityManager->getRepository( MollieUser::class )->findOneBy( [
            'mollie_id' => $customerId,
        ] );

        if ( $existingMollieUser ) {
            $existingMollieUser->setEmail( $email );
            $existingMollieUser->setName( $name );
            $existingMollieUser->setLocale( $locale );

            $this->entityManager->persist( $existingMollieUser );
            $this->entityManager->flush();
        }

        return new JsonResponse( [
            'status' => 'updated',
            'message' => 'User updated successfully',
            '$contumer' => $customer,
        ] );

    }

    #[ Route( '/api/mollie_user_create_mandat', name: 'mollieUserCreateMandat' ) ]

    public function createMollieUserMandat( Request $request ): JsonResponse {
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );
        $customerId = $request->get( 'customerId' );
        $consumerAccount = $request->get( 'consumerAccount' );
        $consumerBic = $request->get( 'consumerBic' );
        $method = $request->get( 'method' );

        $customer = $mollie->customers->get( $customerId );
        $mandates = $customer->mandates();

        if ( $mandates->count() > 0 ) {
            $existingMandate = $mandates->offsetGet( 0 );
            $existingMandate->revoke();

            return new JsonResponse( [
                'status' => 'revoked',
                'message' => 'Mandate revoked successfully',
            ] );
        } else {
            $mandate = $customer->createMandate( [
                'method' => $method,
                'consumerAccount' => $consumerAccount,
                'consumerBic' => $consumerBic,
                'consumerName' => $customer->name,
            ] );

            return new JsonResponse( [
                'status' => 'success',
                'message' => 'Mandate created successfully',
                'mandate' => $mandate,
            ] );
        }
    }

    #[ Route( '/api/mollie_user_create_payment', name: 'mollieUserCreatePayment' ) ]

    public function createMollieUserPayment( Request $request ): JsonResponse {
        $repository = $this->entityManager->getRepository( Invoice::class );
        $invoiceNumber = $request->get( 'invoiceNumber' );

        $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );

        $invoiceToModify = $repository->findOneBy( [
            'invoice_number' => $invoiceNumber,
        ] );

        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );
        $customerId = $request->get( 'customerId' );
        $amount = $request->get( 'amount' );
        $description = $request->get( 'description' );
        $companyName = $request->get( 'companyName' );

        $customer = $mollie->customers->get( $customerId );
        $mandates = $customer->mandates();

        foreach ( $mandates as $key => $mandate ) {
            $payment = $mollie->payments->create( [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => $amount,
                ],
                'metadata' => [
                    'invoice_reference' => $invoiceNumber,
                    'company_name' => $companyName,

                ],
                'description' => $description,
                'mandateId' => $mandate->id,
                'customerId' => $customerId,
                'redirectUrl' => 'https://confident-darwin.212-227-197-242.plesk.page/payment?id=1',

            ] );

            $date = DateTime::createFromFormat( 'd/m/Y', date( 'd/m/Y' ) );
            $transaction = new Transaction();
            $transaction->setInvoiceNumber( $invoiceNumber );
            $transaction->setTransactionAmount( $amount );
            $transaction->setTransactionPaymentMethod( 'virement' );
            $transaction->setCreatedAt( $date );
            $transaction->setTransactionDescription( 'Paiement mollie' );
            $transaction->setMolliePaymentStatus( 'open' );
            $transaction->setMolliePaymentId( $payment->id );
            $transaction->setMollieCustomerId( $customerId );


            $totalInvoice = str_replace(',', '.', $invoiceToModify->getInvoiceAmountTtc());
            $totalInvoice = preg_replace('/[^0-9.]/', '', $totalInvoice);
            $totalInvoice = floatval($totalInvoice); 
            

            $totalPaidInvoice = str_replace(',', '.', $invoiceToModify->getTotalPaid());
            $totalPaidInvoice = preg_replace('/[^0-9.]/', '', $totalPaidInvoice);
            $totalPaidInvoice = floatval($totalPaidInvoice); 
            

            $newAmount = $totalPaidInvoice + $amount;

            $totalPaidInvoice =  $invoiceToModify->setTotalPaid( $newAmount );

            if ( $newAmount >= $totalInvoice ) {
                $invoiceToModify->setPaymentStatus( 'paid' );
            }

            $this->entityManager->persist( $invoiceToModify );
            $this->entityManager->persist( $transaction );
            $this->entityManager->flush();

            return new JsonResponse( [
                'status' => 'success',
                'message' => 'Payment created successfully',
                'payment' => $payment,
            ] );
        }

        return new JsonResponse( [
            'status' => 'failed',
            'message' => 'nomandate',
        ] );

    }

    #[ Route( '/api/checkInvoicesPayment', name: 'checkInvoicesPayment' ) ]

    public function checkInvoicesPayment( Request $request ): JsonResponse {

        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );
        $repository = $this->entityManager->getRepository( Invoice::class );
        $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );

        $molliePaymentId = $request->get( 'molliePaymentId' );

        $payment = $mollie->payments->get( $molliePaymentId );

        $transaction = $repositoryTransaction->findOneBy( [
            'mollie_payment_id' => $molliePaymentId,
        ] );

        $invoice = $repository->findOneBy( [
            'invoice_number' => $transaction->getInvoiceNumber(),
        ] );

        if ( ( $payment->status == 'expired' || $payment->status == 'canceled' || $payment->status == 'failed' ) && $transaction->getMolliePaymentStatus() == 'open' ) {

            
            $invoiceTotal = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
            $invoiceTotal = preg_replace('/[^0-9.]/', '', $invoiceTotal);
            $invoiceTotal = floatval($invoiceTotal); 
            

            $invoiceTotalPaid = str_replace(',', '.', $invoice->getTotalPaid());
            $invoiceTotalPaid = preg_replace('/[^0-9.]/', '', $invoiceTotalPaid);
            $invoiceTotalPaid = floatval($invoiceTotalPaid); 
            

            $diff = $invoiceTotalPaid - $payment->amount->value;

            $transaction->setMolliePaymentStatus( $payment->status );


            if ( $diff <= 0 ) {
                $invoice->setPaymentStatus( 'notPaid' );
                $invoice->setTotalPaid( $diff );
            } else if ( $diff > 0 ) {
                $invoice->setPaymentStatus( 'open' );
                $invoice->setTotalPaid( $diff );
                var_dump( 'diif :', $diff );
            }

        } else if ( $payment->status == 'paid' ) {
            $transaction->setMolliePaymentStatus( $payment->status );
        }

        $this->entityManager->flush();

        return new JsonResponse( [
            'status' => 'uptodate',
            'invoice' => $invoice,
            'transaction' => $transaction,
        ] );
    }

    #[ Route( '/api/checkPaymentsInvoicesNumber', name: 'checkPaymentsInvoicesNumber' ) ]

    public function checkPaymentsInvoicesNumber( Request $request ): JsonResponse {
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );
        $repository = $this->entityManager->getRepository( Invoice::class );
        $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );

        $selectedInvoicesArray = $request->getContent();
        $selectedInvoicesArray = json_decode( $selectedInvoicesArray, true );

        foreach ( $selectedInvoicesArray[ 'invoicesNumber' ] as $invoiceNumber ) {
            $invoice = $repository->findOneBy( [
                'invoice_number' => $invoiceNumber,
            ] );

            $transactions = $repositoryTransaction->findBy( [
                'invoice_number' => $invoiceNumber,
            ] );

            foreach ( $transactions as $key => $transaction ) {
                $paymentId = $transaction->getMolliePaymentId();
                if($paymentId){

                
                $payment = $mollie->payments->get( $paymentId );
                if ( ( $payment->status == 'expired' || $payment->status == 'canceled' || $payment->status == 'failed' ) && $transaction->getMolliePaymentStatus() == 'open' ) {

                   
                    $invoiceTotal = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
                    $invoiceTotal = preg_replace('/[^0-9.]/', '', $invoiceTotal);
                    $invoiceTotal = floatval($invoiceTotal); 
                    
        
                    $invoiceTotalPaid = str_replace(',', '.', $invoice->getTotalPaid());
                    $invoiceTotalPaid = preg_replace('/[^0-9.]/', '', $invoiceTotalPaid);
                    $invoiceTotalPaid = floatval($invoiceTotalPaid); 
                    

                    $diff = $invoiceTotalPaid - $payment->amount->value;

                    $transaction->setMolliePaymentStatus( $payment->status );

                    if ( $diff <= 0 ) {
                        $invoice->setPaymentStatus( 'notPaid' );
                        $invoice->setTotalPaid( $diff );
                    } else if ( $diff > 0 ) {
                        $invoice->setPaymentStatus( 'open' );
                        $invoice->setTotalPaid( $diff );
                        var_dump( 'diif :', $diff );
                    }

                } else if ( $payment->status == 'paid' ) {
                    $transaction->setMolliePaymentStatus( $payment->status );
                }

                $this->entityManager->flush();

            }
        }

        }

        return new JsonResponse( [
            'status' => 'uptodate',
        ] );

    }

    #[ Route( '/api/getAllMandates', name: 'getAllMandates' ) ]

    public function getAllMandates( Request $request ): JsonResponse {
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $customers = $mollie->customers->page();

        $mandatesArray = [];
        foreach ( $customers as $key => $value ) {
            $customer = $mollie->customers->get( $value->id );
            $mandates = $customer->mandates();
            if ( $mandates->count() > 0 ) {
                array_push( $mandatesArray, $mandates );
            }

        }

        return new JsonResponse( [
            'status' => 'success',
            'mandates' => $mandatesArray,
        ] );

    }

    #[ Route( '/api/getAllPayments', name: 'getAllPayments' ) ]

    public function getAllPayments( Request $request ): JsonResponse {

        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $page = $request->query->getInt( 'page', 1 );
        $limit = $request->query->getInt( 'limit', 50 );

        $offset = ( $page - 1 ) * $limit;

        $payments = $mollie->payments->page()->getArrayCopy();
        // $payments = $mollie->payments->page( $offset, $limit )->getArrayCopy();

        return new JsonResponse( [
            'status' => 'success',
            'payments' => $payments,
        ] );
    }

    #[ Route( '/api/getMandatByCustomerId', name: 'getMandatByCustomerId' ) ]

    public function getMandatByCustomerId( Request $request ): JsonResponse {
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $customerId = $request->get( 'customerId' );
        $customer = $mollie->customers->get( $customerId );
        $mandates = $customer->mandates();

        return new JsonResponse( [
            'status' => 'success',
            'mandates' => $mandates,
        ] );
    }

    #[ Route( '/api/mollie_payment_delete', name: 'mollie_payment_delete' ) ]

    public function deleteMolliePaymentById( Request $request ): JsonResponse {
        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );

        $paymentId = $request->get( 'paymentId' );
        try {
            $mollie->payments->delete( $paymentId );
            return new JsonResponse( [
                'status' => 'deleted',
            ] );
        } catch ( \Exception $e ) {
            return new JsonResponse( [
                'status' => 'error',
            ] );
        }
    }

    #[ Route( '/api/mollie_user_delete', name: 'mollie_user_delete' ) ]

    public function deleteMollieUserById( Request $request ): JsonResponse {

        $mollie = new MollieApiClient();
        $mollie->setApiKey( $this->mollieApiKey );
        $customerId = $request->get( 'customerId' );

        try {
            $mollie->customers->delete( $customerId );
            return new JsonResponse( [
                'status' => 'deleted',
            ] );
        } catch ( \Exception $e ) {
            return new JsonResponse( [
                'status' => 'error',
            ] );
        }

    }

}
