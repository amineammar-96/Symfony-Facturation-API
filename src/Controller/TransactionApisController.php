<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\Invoice;
use App\Entity\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use DateTime;

class TransactionApisController extends AbstractController {

    private $entityManager;

    public function __construct( EntityManagerInterface $entityManager ) {
        $this->entityManager = $entityManager;
    }

    #[ Route( '/transaction/apis', name: 'app_transaction_apis' ) ]

    public function index(): Response {
        return $this->render( 'transaction_apis/index.html.twig', [
            'controller_name' => 'TransactionApisController',
        ] );
    }

    #[ Route( '/api/addNewTransactionByInvoice', name: 'addNewTransactionByInvoice' ) ]

    public function addNewTransactionByInvoice( Request $request ): Response {
        $repository = $this->entityManager->getRepository( Transaction::class );
        $repositoryInvoice = $this->entityManager->getRepository( Invoice::class );

        try {
            $invoice = $repositoryInvoice->findOneBy( [
                'invoice_number' => $request->get( 'invoiceNumber' ),
            ] );

            $invoiceNumber = $request->get( 'invoiceNumber' );
            $description = $request->get( 'description' );
            $method = $request->get( 'method' );
            $amount = $request->get( 'amount' );
            $date = $request->get( 'date' );
            $created_at = DateTime::createFromFormat( 'd/m/Y', $date );

            $transaction = new Transaction();

            $transaction->setInvoiceNumber( $invoiceNumber );
            $transaction->setTransactionAmount( $amount );
            $transaction->setTransactionPaymentMethod( $method );
            $transaction->setCreatedAt( $created_at );
            $transaction->setTransactionDescription( $description );

            $this->entityManager->persist( $transaction );

            $this->entityManager->flush();


            $lastAmount = floatval( $invoice->getTotalPaid() );

            $totalInvoiceAmount = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
            $totalInvoiceAmount = preg_replace('/[^0-9.]/', '', $totalInvoiceAmount);
            $totalInvoiceAmount = floatval($totalInvoiceAmount); 
            

            if($totalInvoiceAmount <= $lastAmount + floatval( $amount)){
                $invoice->setPaymentStatus("paid");

            }else  {
                $invoice->setPaymentStatus("open");

            }


            $invoice->setTotalPaid( $lastAmount + floatval( $amount ) );

            $this->entityManager->persist( $invoice );
            $this->entityManager->flush();


            $response = new JsonResponse( [
                'status' => 'success',
                'transaction' => $transaction,
            ] );

            return $response;
        } catch ( \Exception $e ) {
            $errorMessage = $e->getMessage();
            $errorResponse = new JsonResponse( [
                'status' => 'error',
                'message' => 'An error occurred while retrieving invoices.',
                'errorDetails' => $errorMessage,
            ], 500 );

            return $errorResponse;
        }

    }
    #[ Route( '/api/getInvoiceTransactionByInvoiceNumber', name: 'getInvoiceTransactionByInvoiceNumber' ) ]

    public function getInvoiceTransactionByInvoiceNumber( Request $request ): Response {
        $repository = $this->entityManager->getRepository( Transaction::class );
        $transactions = $repository->findBy( [
            'invoice_number' => $request->get( 'invoiceNumber' )
        ] );

        $res = [];

        foreach ( $transactions as $transaction ) {
            $jsonArray = [
                'id' => $transaction->getId(),
                'invoice_number' => $transaction->getInvoiceNumber(),
                'amount' => $transaction->getTransactionAmount(),
                'paymentMethod' => $transaction->getTransactionPaymentMethod(),
                'Date' => $transaction->getCreatedAt(),
                'molliePaymentId' => $transaction->getMolliePaymentId(),
                'description' => $transaction->getTransactionDescription(),
                'status' => $transaction->getMolliePaymentStatus(),
                'paiementId' => $transaction->getMolliePaymentId(),
            ];

            array_push( $res, $jsonArray );
        }

        $response = new JsonResponse( [
            'status' => 'success',
            'transaction' => $res,
        ] );

        return $response;
    }


    #[ Route( '/api/deleteTransactionHorsMolliePayments', name: 'deleteTransactionHorsMolliePayments' ) ]

    public function deleteTransactionHorsMolliePayments( Request $request ): Response {
        $repository = $this->entityManager->getRepository( Transaction::class );
        $repositoryInvoice = $this->entityManager->getRepository( Invoice::class );


        $transaction = $repository->findOneBy( [
            'id' => $request->get( 'transactionId' )
        ]);

       
        $invoiceNumber = $transaction->getInvoiceNumber();
        $invoice = $repositoryInvoice->findOneBy( [
            'invoice_number' => $invoiceNumber,
        ]);

        $totalInvoiceAmount = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
        $totalInvoiceAmount = preg_replace('/[^0-9.]/', '', $totalInvoiceAmount); 
        $totalInvoiceAmount = floatval($totalInvoiceAmount); 
       

        $lastPaidAmount =  $invoice->getTotalPaid();
        $lastPaymentStatus =  $invoice->getPaymentStatus();


        $transactionAmount =  $transaction->getTransactionAmount();
        
        $diff = $lastPaidAmount - $transactionAmount ;


        if( 0 ==  $lastPaidAmount - $transactionAmount){
            $invoice->setPaymentStatus("notPaid");
            $invoice->setTotalPaid($diff);
        }else {
            $invoice->setTotalPaid($diff);
            $invoice->setPaymentStatus("open");

        }



        $this->entityManager->persist($invoice); 
        $this->entityManager->remove($transaction); 
        $this->entityManager->flush();

        


        $response = new JsonResponse( [
            'status' => 'deleted',
        ] );

        return $response;

    }





}
