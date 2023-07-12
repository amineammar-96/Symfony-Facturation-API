<?php

namespace App\Controller;
use League\Csv\Reader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Invoice;
use App\Entity\Transaction;


use Psr\Log\LoggerInterface;
use App\Form\CsvUploadFileFormType;
use Symfony\Component\Mime\Part\DataPart;

use League\Csv\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

use FPDF;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use setasign\Fpdi\Fpdi;
use Spipu\Html2Pdf\Html2Pdf;
use Knp\Snappy\Pdf;
use ZipArchive;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

class InvoiceApisController extends AbstractController {

    private $entityManager;

    public function __construct( EntityManagerInterface $entityManager,  MailerInterface $mailer, TransportInterface $transport ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->transport = $transport;

    }

    public function downloadPdfGlobalInvoice( $invoices  ): Response {
       
        $repository = $this->entityManager->getRepository( Invoice::class );

        $subInvoices = [];

        $globalInvoiceDetails=[];

        $totalAmountTtc = 0;
        $totalTva = 0;
        $subInvoices = [];
        $totalAmountHt = 0;

        foreach ($invoices as $invoice) {

            $amount = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
            $amount = preg_replace('/[^0-9.]/', '', $amount); 
            $amount = floatval($amount); 
           




            $tva = str_replace(',', '.', $invoice->getInvoiceTaxAmount());
            $tva = preg_replace('/[^0-9.]/', '', $tva); 
            $tva = floatval($tva); 
           

            $amountHt = str_replace(',', '.', $invoice->getInvoiceAmountHt());
            $amountHt = preg_replace('/[^0-9.]/', '', $amountHt); 
            $amountHt = floatval($amountHt); 
           
          
            $amount = ($amount);
            $tva = ($tva);
            $amountHt = ($amountHt);
            $totalAmountTtc += $amount;
            $totalTva += $tva;
            $totalAmountHt+=$amountHt;
        
            $subInvoice = [
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'amount' => $amount,
                'tva' => $tva,
                'amountHt' => $amountHt,
                'description' => $invoice->getCompanyName().'--'.$invoice->getClientName().'--'.$invoice->getInvoiceNumber().'('.$invoice->getInvoiceDate().')' , 
            ];
            $subInvoices[] = $subInvoice;
        }


        $periodeValue = str_replace('Période : ', '', $invoices[0]->getInvoicePeriode());
        $periodeValue = strtoupper($periodeValue);

        setlocale(LC_TIME, 'fr_FR.utf8');

        $monthMap = array(
            'JANVIER' => '01',
            'FÉVRIER' => '02',
            'MARS' => '03',
            'AVRIL' => '04',
            'MAI' => '05',
            'JUIN' => '06',
            'JUILLET' => '07',
            'AOÛT' => '08',
            'SEPTEMBRE' => '09',
            'OCTOBRE' => '10',
            'NOVEMBRE' => '11',
            'DÉCEMBRE' => '12'
        );

        $parts = explode(' ', $periodeValue);
        $month = $parts[0];
        $year = $parts[1];

        if (isset($monthMap[$month])) {
            $numericMonth = $monthMap[$month];
            $formattedMonth = $numericMonth . '-' . $year;
            $formattedMonth = 'M'.$formattedMonth;

        } else {
        }




        $globalInvoiceDetails= [
        'adresse' => $invoices[0]->getClientCompanyAddress(),
        'client' => $invoices[0]->getClientName(),
        'company' => $invoices[0]->getCompanyName(),
        'description' => $invoices[0]->getInvoiceServiceDescription(),
        'date' => $invoices[0]->getInvoiceDate(),
        'total' => $totalAmountTtc,
        'totalTva' => $totalTva,
        'city' => $invoices[0]->getClientAddressCity(),
        'cp' => $invoices[0]->getClientCompanyPostalCode(),
        'period' => 'Période du '.str_replace('Période : ' , '' , $invoices[0]->getInvoicePeriode()),
        'method' => strtolower($invoices[0]->getInvoicePaymentCondition()),
        'totalAmountHt' => $totalAmountHt,
        'email' =>  $invoices[0]->getEmail(),
        'formattedMonth' => $formattedMonth,
        ];


       
   


        $fileNameText = $invoices[0]->getCompanyName() . '-' . $globalInvoiceDetails["company"].'-'.$globalInvoiceDetails["client"].'-'.$globalInvoiceDetails["period"];


        $fileNameText = preg_replace( '/[^a-zA-Z0-9]/', '_', $fileNameText );

        $html = $this->renderView( 'invoice/globalInvoiceTemplate.html.twig', [ 'globalInvoiceDetails' => $globalInvoiceDetails , 'subInvoices' => $subInvoices  ] );
        $snappy = new Pdf( '/var/www/vhosts/confident-darwin.212-227-197-242.plesk.page/pdf/bin/wkhtmltopdf' );

        $pdfContent = $snappy->getOutputFromHtml( $html );

        $response = new Response( $pdfContent );
        $response->headers->set( 'Content-Type', 'application/pdf' );
        $response->headers->set( 'Content-Disposition', 'attachment; filename="Facture-'.$fileNameText.'.pdf"' );
        $response->headers->set( 'X-FileName', $fileNameText );
        return $response;


    }

    #[ Route( '/api/generateGlobaleInvoice', name: 'generateGlobaleInvoice' ) ]

    public function generateGlobaleInvoice( Request $request): Response {
        $repository = $this->entityManager->getRepository( Invoice::class );

    
        $selectedInvoicesArray = $request->getContent();
        $selectedInvoicesArray = json_decode($selectedInvoicesArray, true);
        
        
        $invoices=[];
        foreach ($selectedInvoicesArray["selectedInvoicesArray"] as $invoiceId) {
            $invoice = $repository->find( $invoiceId);
            array_push($invoices , $invoice);
        }



        $pdfResponse = $this->downloadPdfGlobalInvoice( $invoices );

                

        return $pdfResponse;
    }



    #[ Route( '/api/invoices', name: 'invoicesapi' ) ]

    public function index( Request $request, PaginatorInterface $paginator ): Response {
        $repository = $this->entityManager->getRepository( Invoice::class );
        $queryBuilder = $repository->createQueryBuilder( 'i' );

        $sortOption = $request->get( 'sortOption' );
        $sortColumn = '';
        $invoiceSearch = rtrim( $request->get( 'invoiceSearch' ) );

        $invoicePaymentStatus = rtrim( $request->get( 'filterInvoicesOption' ) );


            $queryBuilder->orderBy( 'i.id', 'DESC' );
            if ( $sortOption ) {
                $sortDirection = substr( $sortOption, -4 );

                if ( $sortDirection === 'Desc' ) {
                    $sortColumn = substr( $sortOption, 0, -4 );

                    
                        $queryBuilder->orderBy( 'i.' . $sortColumn, 'DESC' );
                    
                } else {
                    $sortColumn = substr( $sortOption, 0, -3 );

                  
                        $queryBuilder->orderBy( 'i.' . $sortColumn, 'ASC' );
                    
                }
            }

            $ref = $request->get( 'ref' );
            $searchValue = $request->get( 'search' );

            if ( $invoicePaymentStatus!=""  ) {
                $queryBuilder
                ->andWhere( 'LOWER(i.company_name) LIKE :search' )
                ->andWhere( 'LOWER(i.invoice_number) LIKE :invoiceSearch' )
                ->andWhere( 'LOWER(i.payment_status) LIKE :paymentStatus' )

                ->setParameters( [
                    'search' => '%' . strtolower( $searchValue ) . '%',
                    'invoiceSearch' => '%' . strtolower( $invoiceSearch ) . '%',
                    'paymentStatus' => '%' . ( $invoicePaymentStatus ) . '%',


                ] );
            } else {
                $queryBuilder
                ->andWhere( 'LOWER(i.company_name) LIKE :search' )
                ->andWhere( 'LOWER(i.invoice_number) LIKE :invoiceSearch' )
                ->setParameters( [
                    'search' => '%' . strtolower( $searchValue ) . '%',
                    'invoiceSearch' => '%' . strtolower( $invoiceSearch ) . '%',

                ]);
            }


            // if($invoicePaymentStatus!=""){
            //     $queryBuilder
            //     ->where('LOWER(i.payment_status) = :paymentStatusOption')
            //     ->setParameters( [
            //         'paymentStatusOption' => strtolower($invoicePaymentStatus),
            //     ]);
            // }



            $results = $queryBuilder->getQuery()->getResult();

            $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);
            if ($sortOption === 'invoice_amount_ttcDesc') {
                usort($results, function ($a, $b) use ($formatter) {
                    $amountA = (float)$this->parseCurrencyValue($a->getInvoiceAmountTtc(), $formatter);
                    $amountB = (float)$this->parseCurrencyValue($b->getInvoiceAmountTtc(), $formatter);

                    return $amountB <=> $amountA;
                });
            } elseif ($sortOption === 'invoice_amount_ttcAsc') {
                usort($results, function ($a, $b) use ($formatter) {
                    $amountA =(float)$this->parseCurrencyValue($a->getInvoiceAmountTtc(), $formatter);
                    $amountB = (float)$this->parseCurrencyValue($b->getInvoiceAmountTtc(), $formatter);

                    return $amountA <=> $amountB;
                });
            }


            $startDate = $request->get( 'startDate' );
            $endDate = $request->get( 'endDate' );

           
            
            $filteredResults = array_filter($results, function($invoice) use ($startDate, $endDate) {
                $invoiceDate = $invoice->getInvoiceDate(); 

                $startDate = DateTime::createFromFormat( 'd/m/Y', $startDate )->setTime( 0, 0, 0 );
                $endDate = DateTime::createFromFormat( 'd/m/Y', $endDate )->setTime( 23, 59, 59 );
        
                $date = DateTime::createFromFormat('d/m/Y', $invoiceDate);


        
             

                return $date >= $startDate && $date <= $endDate;
            });

            
            $invoiceListCount = $request->get( 'invoiceListCount' );


            $pagination = $paginator->paginate(
                $filteredResults,
                $request->query->getInt( 'page', $request->get("page") ),
                $invoiceListCount,
            );

            


            $resArray = [];
            foreach ( $pagination as $key => $invoice ) {
                $jsonArray = [
                    'id' => $invoice->getId(),
                    'client' => $invoice->getClientName(),
                    'invoiceNumber' => $invoice->getInvoiceNumber(),
                    'invoiceDate' => $invoice->getInvoiceDate(),
                    'invoicePeriode' => $invoice->getInvoicePeriode(),
                    'description' => $invoice->getInvoiceServiceDescription(),
                    'total' => $invoice->getInvoiceAmountTtc(),
                    'companyName' => $invoice->getCompanyName(),
                    'invoiceRef' => $invoice->getRelatedInvoiceRef(),
                    'tax' => $invoice->getInvoiceTaxAmount(),
                    'email' => $invoice->getEmail(),
                    'totalPaid' => $invoice->getTotalPaid(),
                    'paymentStatus' => $invoice->getPaymentStatus(),
                ];
                array_push( $resArray, $jsonArray );
            }
            
            
            $invoicesUniqueRefArray = $repository->createQueryBuilder( 'i' )
            ->select( 'DISTINCT i.related_invoice_ref' )
            ->getQuery()
            ->getResult();
            $response = new JsonResponse( [
                'status' => 'success',
                'invoices' => $resArray,
                'currentPage' => $pagination->getCurrentPageNumber(),
                'totalPages' => $pagination->getPageCount(),
                'allInvoicesCount' => count( $resArray ),
                'invoicesUniqueRefArray' => $invoicesUniqueRefArray,
                'filteredResultsCount' => count($filteredResults),
            ] );
            return $response;
    }



    #[ Route( '/api/getInvoiceDetailsById', name: 'getInvoiceDetailsById' ) ]
    public function getInvoiceDetailsById( Request $request): Response {
        $repository = $this->entityManager->getRepository( Invoice::class );
        $invoice = $repository->findOneBy([
            'invoice_number' => $request->get('invoice_number')
        ]);


        $jsonArray=[];
        
            if ($invoice) {
                $jsonArray = [
                    'id' => $invoice->getId(),
                    'client' => $invoice->getClientName(),
                    'invoiceNumber' => $invoice->getInvoiceNumber(),
                    'invoiceDate' => $invoice->getInvoiceDate(),
                    'invoicePeriode' => $invoice->getInvoicePeriode(),
                    'description' => $invoice->getInvoiceServiceDescription(),
                    'total' => $invoice->getInvoiceAmountTtc(),
                    'companyName' => $invoice->getCompanyName(),
                    'invoiceRef' => $invoice->getRelatedInvoiceRef(),
                    'tax' => $invoice->getInvoiceTaxAmount(),
                    'email' => $invoice->getEmail(),
                    'totalPaid' => $invoice->getTotalPaid(),
                    'postalCode' => $invoice->getClientCompanyPostalCode(),
                    'city' => $invoice->getClientAddressCity(),
                    'address' => $invoice->getClientCompanyAddress(),


                ];
            }

            

            

            $response = new JsonResponse( [
                'status' => 'success',
                'invoice' => $jsonArray,
            ] );

            return $response;
        } 
        
        
    

    

    
   

   
   
        #[ Route( '/api/invoices_generate', name: 'invoiceGenerate' ) ]

    public function addNewInvoice( Request $request, LoggerInterface $logger, SessionInterface $session ): Response {
        $uploadedFile = $request->files->get( 'file' );
        $startDate = $request->get( 'start' );
        $endDate = $request->get( 'end' );
        if ( $uploadedFile->isValid() ) {
            $fileContents = file_get_contents( $uploadedFile->getRealPath() );
            $csvData = $this->parseCsvFile( $uploadedFile );
            $filteredInvoices = [];
            $filterKeySearch = 'date_fact';
            $indexKeyDate = array_search( $filterKeySearch, array_values( $csvData[ 0 ] ) );
            foreach ( $csvData as $key => $row ) {
                if ( $key === 0 ) {
                    array_push( $filteredInvoices, $row );
                }
                if ( ( isset( $row[ $indexKeyDate ] ) && $key > 0 ) ) {
                    $invoiceDate = $row[ $indexKeyDate ];
                    if($invoiceDate !=""){
                        if ( $this->isWithinDateRange( $invoiceDate, $startDate, $endDate ) ) {
                            array_push( $filteredInvoices, $row );
                        }
                    }
                    
                }
            }

           

            $headerRow = array_shift( $filteredInvoices );
            array_unshift( $filteredInvoices, $headerRow );





            $uniqueId = substr( uniqid(), 6 ) . rand( 10, 99 );
            $uniqueId = strtoupper( $uniqueId );
            $uniqueIdAux = date( 'd-m-y' );
            $pdfFileName = 'REF Lot - ' . $uniqueIdAux . ' - ' . $uniqueId;

            $invoices = $this->createInvoicesFromCsvData( $filteredInvoices, $pdfFileName );

            $invoicesToGenerate = [];



            foreach ( $invoices as $invoice ) {
                $existingInvoice = $this->entityManager->getRepository( Invoice::class )->findOneBy( [
                    'invoice_number' => $invoice->getInvoiceNumber(),
                ] );

                if ( !$existingInvoice ) {
                    array_push( $invoicesToGenerate, $invoice );
                }
            }





            if ( count( $invoicesToGenerate )>0 ) {

              
                $pdfResponse = $this->downloadPdfFiles( $invoicesToGenerate,  $session, $pdfFileName );

                return new JsonResponse( [
                    'pdfResponse' => $pdfResponse,
                    'invoicesToGenerate' => count ($invoicesToGenerate),
                ] , 200 );

                return $pdfResponse;
            } else {
                return new JsonResponse( [
                    'message' => 'nofiles',
                ] , 500 );
            }
        } else {
            $errorMessage = 'No valid file uploaded';
            if ( $uploadedFile instanceof UploadedFile ) {
                $errorCode = $uploadedFile->getError();
                $errorMessage = 'File upload error: ' . $errorCode;
            }

            return new JsonResponse( [
                'message' => $errorMessage,
            ] );
        }
    }

    #[ Route( '/api/invoices_progress', name: 'invoiceGenerationProgress' ) ]

    public function getInvoiceGenerationProgress( SessionInterface $session ): Response {
        
        $progress = $session->get( 'invoice_generation_progress', 0 );

        return new JsonResponse( [
            'progress' => $session->get( 'invoice_generation_progress', 0 ),
        ] );
    }

    public function downloadPdfFiles( $invoices,  $session, $pdfFileName ): Response {
        $basePath = 'pdf/';
        $zipPath = $basePath . 'factures-saps.zip';
        $zip = new ZipArchive();
        $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );

        $totalInvoices = count( $invoices );
        $invoicesGenerated = 0;

        foreach ( $invoices as $invoice ) {

            $html = $this->renderView( 'invoice/invoices.html.twig', [ 'invoices' => [ $invoice ] ] );
            $snappy = new Pdf( '/var/www/vhosts/confident-darwin.212-227-197-242.plesk.page/pdf/bin/wkhtmltopdf' );
            $pdfContent = $snappy->getOutputFromHtml( $html );

            $date = \DateTime::createFromFormat( 'm/d/Y', $invoice->getInvoiceDate() );
            $date->modify( 'first day of this month' );
            $firstDay = $date->format( 'd.m.y' );
            $date->modify( 'last day of this month' );
            $lastDay = $date->format( 'd.m.y' );

            $periodeAux = str_replace("Période : " , '' , $invoice->getInvoicePeriode());

            $periodeAux = str_replace(' ' , '-' ,  $periodeAux);

            $fileNameText = $periodeAux.'-' .$invoice->getCompanyName().'-'.$invoice->getInvoiceNumber();

            $fileNameText = preg_replace( '/[^a-zA-Z0-9]/', '-', $fileNameText );

            $pdfPath = $basePath.$fileNameText.'.pdf';

            file_put_contents( $pdfPath, $pdfContent );

            $zip->addFile( $pdfPath, basename( $pdfPath ) );
            $invoicesGenerated++;
            $progress = round( ( $invoicesGenerated / $totalInvoices ) * 100 );

           
            
            $session->set( 'invoice_generation_progress', $progress );

           
        }

        $zip->close();
        $response = new Response();

        if ( file_exists( $zipPath ) ) {
            $response = new Response( file_get_contents( $zipPath ) );
            $response->headers->set( 'Content-Type', 'application/zip' );
            $response->headers->set( 'Content-Disposition', 'attachment; filename="factures-saps.zip"' );
            $pdfFiles = glob( $basePath . '*.pdf' );
            foreach ( $pdfFiles as $pdfFile ) {
                unlink( $pdfFile );
            }
            if ( file_exists( $zipPath ) ) {
                unlink( $zipPath );
            }
            foreach ( $invoices as $invoice ) {
                $this->entityManager->persist( $invoice );
            }
            $this->entityManager->flush();
        
            return $response;

        } else {
            return $response;

        }

    }

    

    public function parseCurrencyValue($currencyString, $formatter)
    {
       // Remove any non-numeric characters from the currency string
       $cleanedString = preg_replace('/[^0-9.,]/', '', $currencyString);
       $cleanedString = str_replace(',', '', $cleanedString);

   

       return $cleanedString;
   }
    

    private function parseCsvFile( $csvFile ) {
        $csvReader = Reader::createFromPath( $csvFile->getPathname() );
        $csvReader->setDelimiter( ';' );

        $csvRecords = $csvReader->getRecords();

        $csvData = [];
        foreach ( $csvRecords as $record ) {
            $hasNullColumn = false;
            foreach ( $record as $column ) {
                if ( $column === null ) {
                    $hasNullColumn = true;
                    break;
                }
            }
            if ( !$hasNullColumn ) {
                $csvData[] = $record;
            }
        }


        return $csvData;

    }

    private function createInvoicesFromCsvData( $filteredInvoices, $pdfFileName ) {
        $invoices = [];

        // dd($filteredInvoices);

        $header = null;
        foreach ( $filteredInvoices as $filteredInvoice ) {
            if ( $header === null ) {
                $header = $filteredInvoice;
                continue;
            }
            $invoice = new Invoice();
            $invoice->setRelatedInvoiceRef( $pdfFileName );
            $invoice->setTotalPaid( 0 );
            $invoice->setPaymentStatus('notPaid');

            foreach ( $filteredInvoice as $index => $value ) {
                $key = trim( $header[ $index ] );

                // $decodedKey = iconv('ISO-8859-1', 'UTF-8', $key);
                // $value = iconv( 'UTF-8', $value);
                // $decodedKey = str_replace('Ž', 'é', $decodedKey);

                // var_dump($value);

                switch ( $key ) {
                    case 'Numéro facture':
                    $invoice->setInvoiceNumber($value);
                    break;
                    case 'Montant1':
                    $invoice->setInvoiceAmountHt( str_replace('€' , '' , $value ));
                    break;
                    case 'TVA':
                    $invoice->setInvoiceTaxAmount(  str_replace('€' , '' , $value ) );
                    break;
                    case 'Cabinet':
                    $invoice->setCompanyName( $value );
                    break;
                    case 'A qui':
                    $invoice->setClientName( $value );
                    break;
                    case 'Adress1':
                    $invoice->setClientCompanyAddress( $value );
                    break;
                    case 'CP':
                    $invoice->setClientCompanyPostalCode( $value );
                    break;
                    case 'Ville':
                    $invoice->setClientAddressCity( $value );
                    break;
                    case 'date_fact':
                    $invoice->setInvoiceDate( $value );
                    break;
                    case 'email':
                    $invoice->setEmail( $value );
                    break;
                    case 'Presta2':
                    $invoice->setInvoicePeriode( $value );
                    break;
                    case 'Presta1':
                    $invoice->setInvoiceServiceDescription( $value );
                    break;
                    case 'Net':
                    $invoice->setInvoiceAmountTtc(  str_replace('€' , '' , $value ) );
                    break;
                    case 'Condition_reglement':
                    $invoice->setInvoicePaymentCondition( $value );
                    break;
                }

            }
            $invoices[] = $invoice;

        }


        return $invoices;
    }

    #[ Route( '/api/download_invoice/{id}', name: 'download_invoice' ) ]

    public function downloadOneInvoice( Request $request ): Response {
        $repository = $this->entityManager->getRepository( Invoice::class );
        $invoice = $repository->find( $request->get( 'id' ) );
        $invoiceList = [];

        array_push( $invoiceList, $invoice );
        if ( !$invoice ) {
            throw $this->createNotFoundException( 'Invoice not found' );
        }


        foreach ($invoiceList as $key => $invoice) {
            $amount = $invoice->getInvoiceAmountTtc();
            $amount = str_replace(',', '.', $amount); 
            $amount = preg_replace('/[^0-9.]/', '', $amount);
            $invoice->setInvoiceAmountTtc($amount);
        }




        $date = \DateTime::createFromFormat( 'm/d/Y', $invoice->getInvoiceDate() );
        $date->modify( 'first day of this month' );
        $firstDay = $date->format( 'd.m.y' );
        $date->modify( 'last day of this month' );
        $lastDay = $date->format( 'd.m.y' );

        $fileNameText = $firstDay . '-' . $lastDay . '-' . $invoice->getCompanyName() . '-' . $invoice->getInvoiceNumber();

        $fileNameText = preg_replace( '/[^a-zA-Z0-9]/', '_', $fileNameText );

        $html = $this->renderView( 'invoice/invoices.html.twig', [ 'invoices' => $invoiceList ] );
        $snappy = new Pdf( '/var/www/vhosts/confident-darwin.212-227-197-242.plesk.page/pdf/bin/wkhtmltopdf' );

        $pdfContent = $snappy->getOutputFromHtml( $html );

        $response = new Response( $pdfContent );
        $response->headers->set( 'Content-Type', 'application/pdf' );
        $response->headers->set( 'Content-Disposition', 'attachment; filename="Facture-'.$fileNameText.'.pdf"' );
        $response->headers->set( 'X-FileName', $fileNameText );
        return $response;
    }

    private function isWithinDateRange( string $date, string $fromDate, string $toDate ): bool {
      
        
        // dd($date , $fromDate , $endDate);

        $fromDateAux = DateTimeImmutable::createFromFormat( 'd/m/Y', $fromDate )->setTime( 0, 0, 0 );
        $toDateAux = DateTimeImmutable::createFromFormat( 'd/m/Y', $toDate )->setTime( 23, 59, 59 );
        $dateAux = DateTimeImmutable::createFromFormat( 'd/m/Y', $date )->setTime( 0, 0, 0 );

        
        return $dateAux >= $fromDateAux && $dateAux <= $toDateAux;

    }

    #[ Route( '/api/invoice_send_mail', name: 'invoiceSendMail' ) ]

    public function sendInvoiceMail( Request $request ): JsonResponse {
       
    $invoiceMap = []; 
    $selectedInvoicesArray = $request->getContent();
    $selectedInvoicesArray = json_decode($selectedInvoicesArray, true);



foreach ($selectedInvoicesArray["selectedInvoicesArray"] as $invoiceId) {

    var_dump($invoiceId);
    $repository = $this->entityManager->getRepository(Invoice::class);
    $invoice = $repository->findOneBy([
        'id' => $invoiceId,
    ]);

    if (!$invoice) {
        throw $this->createNotFoundException('Invoice not found');
    }

    $email = $invoice->getEmail();
    if($email != ''){
    $indexKey = $email;

    if (!isset($invoiceMap[$indexKey])) {
        $invoiceMap[$indexKey] = [
            'invoices' => [],
            'invoiceNumbers' => [],
            'invoiceDates' => [],
            'invoicePeriode' => [],
            'client' => [],

        ];
    }

    $invoiceMap[$indexKey]['clients'][] = $invoice->getCompanyName();

    $invoiceMap[$indexKey]['invoices'][] = $invoice;
    $invoiceMap[$indexKey]['invoiceNumbers'][] = $invoice->getInvoiceNumber();
    $invoiceMap[$indexKey]['invoiceDates'][] = $invoice->getInvoiceDate();
    $invoiceMap[$indexKey]['invoicePeriode'][] = str_replace(['Période' ,':'],'' ,$invoice->getInvoicePeriode());
    }
}
foreach ($invoiceMap as $email => $data) {
    $invoices = $data['invoices'];
    $invoiceNumbers = $data['invoiceNumbers'];
    $invoiceDates = $data['invoiceDates'];
    $invoiceDatesFormatted = [];
    $invoicePeriodes = $data['invoicePeriode'];
    $clients = $data['clients'];

    foreach ($invoiceDates as $key => $value) {
        $date = DateTime::createFromFormat('d/m/Y', $value);
        $value = $date->format('d-m-Y');
        array_push($invoiceDatesFormatted , $value);
    }

   

    $html = "<html><body><p>Bonjour,  <br/><br/> Vous trouvez ci-joint la facturation SAPS.</p> <br/>";

        for ($i = 0; $i < count($invoiceNumbers); $i++) {
            $invoiceNumber = $invoiceNumbers[$i];
            $invoiceDate = $invoiceDatesFormatted[$i];
            $invoicePeriode = $invoicePeriodes[$i];
            $client = $clients[$i];

            
            $html .= "<p>".($i+1)."-Numéro facture: " . $invoiceNumber . "</p>";
            $html .= "<p>Période : " . $invoicePeriode . "</p>";
            $html .= "<p>Date de facture : " . $invoiceDate . "</p>";
            $html .= "<p>Client : " . $client . "</p><br/><br/>";

            
        }
        
        $html .= "<br/><p>Bonne réception.</p>  <p>Le service comptable SAPS</p> </body></html>";
        
    $message = (new Email())
        ->from('factures@web-saps.fr')
        // ->to($email)
        ->to("amineammar20@icloud.com")
        ->bcc('factures@web-saps.fr')
        ->subject('Facturation SAPS - '.$invoicePeriodes[0])
        ->html($html);

        
    foreach ($invoices as $invoice) {
        $amount = $invoice->getInvoiceAmountTtc();
        $amount = str_replace(',', '.', $amount); 
        $amount = preg_replace('/[^0-9.]/', '', $amount);
        $invoice->setInvoiceAmountTtc($amount);
        
        $html = $this->renderView('invoice/invoices.html.twig', ['invoices' => [$invoice]]);
        $snappy = new Pdf('/var/www/vhosts/confident-darwin.212-227-197-242.plesk.page/pdf/bin/wkhtmltopdf');
        $pdfContent = $snappy->getOutputFromHtml($html);

        $message->attach($pdfContent, $invoice->getCompanyName() . '-' . $invoice->getInvoiceNumber() . '.pdf', 'application/pdf');
    }

    $this->transport->send($message);
}



            return new JsonResponse( [
                'status' => 'success'
            ] );
        

    }

    #[ Route( '/api/invoice_email_update', name: 'invoiceEmailUpdate' ) ]

    public function updateInvoiceEmail( Request $request ): JsonResponse {

        try {
            $repository = $this->entityManager->getRepository( Invoice::class );
            $invoice = $repository->find( $request->get( 'id' ) );
            // dd($invoice);
            $invoice->setEmail( $request->get( 'email' ) );
            $this->entityManager->persist( $invoice );
            $this->entityManager->flush();

            return new JsonResponse( [
                'status' => 'updated',
                'invoice' => $invoice,
            ] );
        } catch ( \Throwable $th ) {
            return new JsonResponse( [
                'status' => 'failed',
                'error' => $th,

            ] );
        }

    }

    #[ Route( '/api/invoice_retreive_payment/{id}', name: 'invoiceRetreiveForPayment' ) ]

    public function invoiceRetreiveForPayment( Request $request ): JsonResponse {

        // try {
            $repository = $this->entityManager->getRepository( Invoice::class );
            $invoice = $repository->find( $request->get( 'id' ) );

            $jsonArray = array(
                'id' => $invoice->getId(),
                'client' => $invoice->getClientName(),
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'invoiceDate' => $invoice->getInvoiceDate(),
                'invoicePeriode' => $invoice->getInvoicePeriode(),
                'description' => $invoice->getInvoiceServiceDescription(),
                'total' => $invoice->getInvoiceAmountTtc(),
                'companyName' => $invoice->getCompanyName(),
                'invoiceRef' => $invoice->getRelatedInvoiceRef(),
                'tax' => $invoice->getInvoiceTaxAmount(),
                'email' => $invoice->getEmail(),
                'totalPaid' => $invoice->getTotalPaid(),
                'paymentStatus' => $invoice->getPaymentStatus(),

            );

            return new JsonResponse( [
                'status' => 'updated',
                'jsonInvoice' => $jsonArray,

            ] );
        // } 
        // catch ( \Throwable $th ) {
        //     return new JsonResponse( [
        //         'status' => 'failed',
        //         'error' => $th,

        //     ] );
        // }

    }


    #[ Route( '/api/invoice_delete', name: 'invoice_delete' ) ]
    public function deleteInvoiceById( Request $request ): JsonResponse {


        $repository = $this->entityManager->getRepository( Invoice::class );
        $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );

        $invoice = $repository->find( $request->get( 'id' ) );

        $invoiceNumber = $invoice->getInvoiceNumber();

        $transactions = $repositoryTransaction->findBy(['invoice_number' => $invoiceNumber]);

        if($transactions){
              foreach($transactions as $transaction){
                $this->entityManager->remove($transaction); 
        }
        $this->entityManager->flush();

        }
      


    if (!$invoice) {
        return new JsonResponse( [
            'status' => 'not found',

        ] );    }

        $this->entityManager->remove($invoice);
        $this->entityManager->flush();



            return new JsonResponse( [
                'status' => 'deleted',

            ] );
        } 
        


        #[ Route( '/api/deleteInvoicesArray', name: 'deleteInvoicesArray' ) ]
        public function deleteInvoicesArray( Request $request ): JsonResponse {
    
    
            $repository = $this->entityManager->getRepository( Invoice::class );
            $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );

            
    
        

            $selectedInvoicesArray = $request->getContent();
            $selectedInvoicesArray = json_decode($selectedInvoicesArray, true);
            

            
            foreach ($selectedInvoicesArray["selectedInvoicesArray"] as $invoiceId) {
                $invoice = $repository->find( $invoiceId);

                $invoiceNumber = $invoice->getInvoiceNumber();
        
                $transactions = $repositoryTransaction->findBy(['invoice_number' => $invoiceNumber]);
        
                if($transactions){
                      foreach($transactions as $transaction){
                        $this->entityManager->remove($transaction); 
                }
                $this->entityManager->flush();
                }

                if (!$invoice) {
                    return new JsonResponse( [
                        'status' => 'not found',
                    ] );    }

                $this->entityManager->remove($invoice);
                $this->entityManager->flush();
            }
                return new JsonResponse( [
                    'status' => 'deleted',
    
                ] );
            } 
            




        #[ Route( '/api/globalInvoicesPayment', name: 'globalInvoicesPayment' ) ]
        public function globalInvoicesPayment( Request $request ): JsonResponse {
    
            
            $repository = $this->entityManager->getRepository( Invoice::class );
            $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );

    

            $selectedInvoicesArray = $request->getContent();
            $selectedInvoicesArray = json_decode($selectedInvoicesArray, true);
            
            
            foreach ($selectedInvoicesArray["selectedInvoicesArray"] as $invoiceId) {

                $invoice = $repository->find( $invoiceId);
                





        $totalPaidAux = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
        $totalPaidAux = preg_replace('/[^0-9.]/', '', $totalPaidAux); 
        $totalPaidAux = floatval($totalPaidAux); 
       

        $totalPaid = str_replace(',', '.', $invoice->getTotalPaid());
        $totalPaid = preg_replace('/[^0-9.]/', '', $totalPaid); 
        $totalPaid = floatval($totalPaid); 
       


                if( $invoice->getPaymentStatus() != "paid"){
                    $invoiceNumber = $invoice->getInvoiceNumber();

                

                
                $date = DateTime::createFromFormat( 'd/m/Y', $invoice->getInvoiceDate());
                $transaction = new Transaction();

            $transaction->setInvoiceNumber( $invoiceNumber );
            $transaction->setTransactionAmount( $totalPaidAux  - $totalPaid );
            $transaction->setTransactionPaymentMethod( "virement" );
            $transaction->setCreatedAt( $date );
            $transaction->setTransactionDescription( "Encaissement" );

            $this->entityManager->persist( $transaction );

            $this->entityManager->flush();
                }


                
        

                $invoice->setTotalPaid(floatval($totalPaidAux));
                $invoice->setPaymentStatus('paid');
                $this->entityManager->persist( $invoice );
            $this->entityManager->flush();

            }
                return new JsonResponse( [
                    'status' => 'success',
    
                ] );
            } 



            #[ Route( '/api/generateCsvFromInvoices', name: 'generateCsvFromInvoices' ) ]
            public function generateCsvFromInvoices( Request $request ): Response {
        
                
                $repository = $this->entityManager->getRepository( Invoice::class );
                $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );
    
        
    
                $selectedInvoicesArray = $request->getContent();
                $selectedInvoicesArray = json_decode($selectedInvoicesArray, true);
                
                $csvData = [];
                $csvFilePath = 'pdf/exportCsv.csv';
                
                
                $indexAux=0;

                foreach ($selectedInvoicesArray["selectedInvoicesArray"] as $invoiceId) {
                    $invoice = $repository->find( $invoiceId);
                    $transactions = $repositoryTransaction->findBy([
                        'invoice_number' => $invoice->getInvoiceNumber(),
                    ]);


                    $transactionsHistory=[];
                    foreach ($transactions as $key => $transaction) {
                        $date = $transaction->getCreatedAt(); 
                        $formattedDate = $date->format('d-m-Y');

                        $transactionsHistory[]=[
                            'amount' => $transaction->getTransactionAmount(),
                            'paymentMethod' => $transaction->getTransactionPaymentMethod(),
                            'date' => $formattedDate,
                            'status' => $transaction->getMolliePaymentStatus(),

                        ];
                    }

                    $stringHistory="";
                    foreach ($transactionsHistory as $key => $history) {
                        if($key != count($transactionsHistory) - 1 ){
                        $stringHistory .= $history['amount'].'€ / '.$history['paymentMethod'].' / '.$history['date']."\n";
                        if ($history['status'] != ""){
                            $stringHistory .='('.$history['status'].')';
                        }
                    }else {
                            $stringHistory .= $history['amount'].'€/'.$history['paymentMethod'].'/'.$history['date'];
                            if ($history['status'] != ""){
                                $stringHistory .='('.$history['status'].')';
                            }
                        }
                    }

                   
                    
                    $invoiceData = [
                        'Actif' => $invoiceId,
                        'Cabinet' => $invoice->getCompanyName(),
                        'A qui' => $invoice->getClientName(),
                        'Adress1' => $invoice->getClientCompanyAddress(),
                        'CP' => $invoice->getClientCompanyPostalCode(),
                        'Ville' => $invoice->getClientAddressCity(),
                        'date_fact' => $invoice->getInvoiceDate(),
                        'Numéro facture' => $invoice->getInvoiceNumber(),
                        'Condition_reglement' => $invoice->getInvoicePaymentCondition(),
                        'Presta2' => $invoice->getInvoicePeriode(),
                        'Montant1' => $invoice->getInvoiceAmountHt(),
                        'TVA' => $invoice->getInvoiceTaxAmount(),
                        'Net' => $invoice->getInvoiceAmountTtc(),
                        'email' => $invoice->getEmail(),
                        'historiquePaiements' => $stringHistory,
                    ];
                    
                    $csvData[] = $invoiceData;
                }
    
                   
    
                $file = fopen($csvFilePath, 'w');
                fprintf($file, "\xEF\xBB\xBF");

                fputcsv($file, array_keys($csvData[0]), ';');
                
                foreach ($csvData as $row) {
                    fputcsv($file, $row, ';'); 
                }
                
                fclose($file);

                $response = new Response(file_get_contents($csvFilePath));
                $response->headers->set('Content-Type', 'text/csv; charset=utf-8'); 
                $response->headers->set('Content-Disposition', 'attachment; filename="invoices.csv"');
                

                return $response;
    
               
                } 





                #[ Route( '/api/generatePdfFromInvoices', name: 'generatePdfFromInvoices' ) ]
                public function generatePdfFromInvoices( Request $request ): Response {
            
                    
                    $repository = $this->entityManager->getRepository( Invoice::class );
                    $repositoryTransaction = $this->entityManager->getRepository( Transaction::class );
        
                    $selectedInvoicesArray = $request->getContent();
                    $selectedInvoicesArray = json_decode($selectedInvoicesArray, true);
                    
                    $pdfData = [];
                    $csvFilePath = 'pdf/exportCsv.csv';
                    
                    
                    $indexAux=0;
    
                    foreach ($selectedInvoicesArray["selectedInvoicesArray"] as $invoiceId) {
                        $invoice = $repository->find( $invoiceId);
                        $transactions = $repositoryTransaction->findBy([
                            'invoice_number' => $invoice->getInvoiceNumber(),
                        ]);
    
    
                        $transactionsHistory=[];
                        foreach ($transactions as $key => $transaction) {
                            $date = $transaction->getCreatedAt(); 
                            $formattedDate = $date->format('d-m-Y');

                            $amount = str_replace(',', '.', $transaction->getTransactionAmount());
                            $amount = preg_replace('/[^0-9.]/', '', $amount); 
                            $amount = floatval($amount); 
                           
                    
    
                            $transactionsHistory[]=[
                                'amount' => $transaction->getTransactionAmount(),
                                'paymentMethod' => $transaction->getTransactionPaymentMethod(),
                                'status' => $transaction->getMolliePaymentStatus(),
                                'date' => $formattedDate,
                            ];
                        }
    


                        $transactionsDetails=[];
                        foreach ($transactionsHistory as $key => $history) {
                            $stringHistory="";
                            $stringHistory .= $history['amount'].'€ / '.$history['paymentMethod'].' / '.$history['date'];
                            if ($history['status'] != ""){
                                $stringHistory .='('.$history['status'].')';
                            }
                            array_push($transactionsDetails , $stringHistory);
                        }
    
                       
                        $dateFact = $invoice->getInvoiceDate();
$formattedDate = DateTime::createFromFormat('m/d/Y', $dateFact)->format('d/m/Y'); 

                        $invoiceData = [
                            'ID' => $invoiceId,
                            'Centre' => $invoice->getCompanyName(),
                            'Client' => $invoice->getClientName(),
                            'Adress' => $invoice->getClientCompanyAddress(),
                            'CP' => $invoice->getClientCompanyPostalCode(),
                            'City' => $invoice->getClientAddressCity(),
                            'date_fact' => $formattedDate,
                            'Nfacture' => $invoice->getInvoiceNumber(),
                            'Periode' => $invoice->getInvoicePeriode(),
                            'amountHt' => $invoice->getInvoiceAmountHt(),
                            'tva' => $invoice->getInvoiceTaxAmount(),
                            'amountTtc' => $invoice->getInvoiceAmountTtc(),
                            'email' => $invoice->getEmail(),
                            'transactions' => $transactionsDetails,
                            'totalPaid' => $invoice->getTotalPaid(),
                            'status' => $invoice->getPaymentStatus(),

                        ];
                        
                        $pdfData[] = $invoiceData;
                    }
        

        $html = $this->renderView( 'invoice/pdfExportedTemplate.html.twig', [ 'invoices' => $pdfData ] );
        $snappy = new Pdf( '/var/www/vhosts/confident-darwin.212-227-197-242.plesk.page/pdf/bin/wkhtmltopdf' );

        $snappy->setOptions(['orientation' => 'Landscape']);
        $pdfContent = $snappy->getOutputFromHtml( $html );

        $response = new Response( $pdfContent );
        $response->headers->set( 'Content-Type', 'application/pdf' );
        $response->headers->set( 'Content-Disposition', 'attachment; filename="Facture.pdf"' );
        return $response;
                       
        
                  
                    
                  
                   
                    } 
    



}