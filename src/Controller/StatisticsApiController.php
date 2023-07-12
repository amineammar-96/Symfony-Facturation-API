<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Invoice;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\InvoiceRepository;

use Knp\Component\Pager\PaginatorInterface;

use Doctrine\ORM\EntityManagerInterface;
use DateTime;

class StatisticsApiController extends AbstractController {
    private $entityManager;

    public function __construct( EntityManagerInterface $entityManager, InvoiceRepository $invoiceRepository ) {
        $this->entityManager = $entityManager;
        $this->invoiceRepository = $invoiceRepository;

    }

    #[ Route( '/api/statistics_invoices_generation', name: 'statistics_invoice_generation' ) ]

    public function getInvoicesGenerationStatistics( Request $request, PaginatorInterface $paginator ): JsonResponse {
        $repository = $this->entityManager->getRepository( Invoice::class );

        $startDate = $request->get( 'startDate' );
        $endDate = $request->get( 'endDate' );

        $query = $repository->createQueryBuilder( 'i' )
        ->select( 'SUBSTRING(i.invoice_date, 1, 2) AS date, SUBSTRING(i.invoice_date, -4) AS dateAux, COUNT(i.id) AS count' )
      
        ->groupBy( 'date' )
        ->addGroupBy( 'dateAux' )
        ->orderBy( 'dateAux', 'ASC' )
        ->addOrderBy( 'date', 'ASC' )
     
        ->getQuery();

        $results = $query->getResult();
        dd($results);
        $statistics = [];
        $returnedRows = 0;
        foreach ( $results as $result ) {
            if ( $returnedRows<6 ) {
                $yearMonth = $result[ 'date' ];
                $yearMonth = preg_replace( '/[^0-9]+/', '', $yearMonth );
                $monthNum=$yearMonth;
                switch ( $yearMonth ) {
                    case '1' : $yearMonth = 'Janvier';
                    break;
                    case '2' : $yearMonth = 'Février';
                    break;
                    case '3' : $yearMonth = 'Mars';
                    break;
                    case '4' : $yearMonth = 'Avril';
                    break;
                    case '5' : $yearMonth = 'Mai';
                    break;
                    case '6' : $yearMonth = 'Juin';
                    break;
                    case '7' : $yearMonth = 'Juillet';
                    break;
                    case '8' : $yearMonth = 'Août';
                    break;
                    case '9' : $yearMonth = 'Septembre';
                    break;
                    case '10' : $yearMonth = 'Octobre';
                    break;
                    case '11' : $yearMonth = 'Novembre';
                    break;
                    case '12' : $yearMonth = 'Décembre';
                    break;

                }
                $dateAux = $result[ 'dateAux' ];
                $count = $result[ 'count' ];
                $statistics[] = [
                    'dateAux'=>$dateAux,
                    'date' => $yearMonth,
                    'count' => $count,
                    'month' =>$monthNum,
                ];
            }
            // $returnedRows++;
        }

        $startDate = DateTime::createFromFormat( 'd/m/Y', $startDate )->setTime( 0, 0, 0 );
        $endDate = DateTime::createFromFormat( 'd/m/Y', $endDate )->setTime( 23, 59, 59 );

    


        $filteredStatistics = [];
        foreach ( $statistics as $stat ) {
            $date = DateTime::createFromFormat('d/m/Y', '01/' . $stat['month'] . '/' . $stat['dateAux']);

            if ($date >= $startDate && $date <= $endDate ) {
                $filteredStatistics[] = $stat;
            }
        }

        return new JsonResponse( [
            'stats'=>$filteredStatistics,
            'results'=>$results,
            'start' => $startDate,
            'end' => $endDate,
        ] ) ;

    }

    #[ Route( '/api/statistics_invoices_allAmounts/', name: 'statistics_invoices_allAmounts' ) ]

    public function getInvoicesAmounts( Request $request ): JsonResponse {
        $repository = $this->entityManager->getRepository( Invoice::class );

        $startDate = $request->get( 'startDate' );
        $endDate = $request->get( 'endDate' );

        $query = $repository->createQueryBuilder( 'i' )
        ->select( 'i.invoice_date as dateInvoice , i.total_paid as totalPaid, i.invoice_amount_ttc as amountTTC, i.invoice_tax_amount as tvaTax' )
        ->getQuery();

        $invoices = $query->getResult();


        $filteredStatistics = [];
        if($startDate && $endDate){

   
        $startDate = DateTime::createFromFormat( 'd/m/Y', $startDate )->setTime( 0, 0, 0 );
        $endDate = DateTime::createFromFormat( 'd/m/Y', $endDate )->setTime( 23, 59, 59 );


        foreach ( $invoices as $invoice ) {

            $date = DateTime::createFromFormat('d/m/Y', $invoice['dateInvoice']);
            if ( $date >= $startDate && $date <= $endDate ) {
                $filteredStatistics[] = $invoice;
            }

        }
    }


        $totalAmountTTC = 0;
        $totalTvaTax = 0;
        $totalAmountTTCPaid = 0;

        foreach ( $filteredStatistics as $invoice ) {

            $amountTTCPaid = str_replace(',', '.', $invoice[ 'totalPaid' ] );
            $amountTTCPaid = preg_replace('/[^0-9.]/', '', $amountTTCPaid); 
            $amountTTCPaid = floatval($amountTTCPaid); 
           
            
            $amountTTC = str_replace(',', '.', $invoice[ 'amountTTC' ] );
            $amountTTC = preg_replace('/[^0-9.]/', '', $amountTTC); 
            $amountTTC = floatval($amountTTC); 
           
    
            $tvaTax = str_replace(',', '.', $invoice[ 'tvaTax' ] );
            $tvaTax = preg_replace('/[^0-9.]/', '', $tvaTax); 
            $tvaTax = floatval($tvaTax); 
           

            $totalAmountTTC += $amountTTC;
            $totalTvaTax += $tvaTax;
            $totalAmountTTCPaid+= $amountTTCPaid;
        }

        // $totalAmountTVAPaid = ($totalAmountTTCPaid) * 0.166666;

        $totalAmountTVAPaid = 0;

       

        return new JsonResponse( [
            'totalAmountTTC' => $totalAmountTTC,
            'totalTvaTax' => $totalTvaTax,
            'totalTvaTax' => $totalTvaTax,
            'amountTtcSumPaid' => $totalAmountTTCPaid,
            'countInvoices'=> count( $filteredStatistics ),
            'totalAmountTVAPaid' => $totalAmountTVAPaid,
            'start' => $startDate,
            'end' => $endDate,
        ] );
    }


    #[ Route( '/api/statistics_invoices_amounts/', name: 'statistics_invoices_amounts' ) ]

    public function getInvoicesGenerationStatisticsAndAmounts( Request $request, PaginatorInterface $paginator ): JsonResponse {

        $repository = $this->entityManager->getRepository( Invoice::class );
        $startDate = $request->get( 'startDate' );
        $endDate = $request->get( 'endDate' );

        $invoices = $repository->findAll();

$statistics = [];

$invoiceCount = 0;
    $totalTTC = 0;
    $totalTVA = 0;
foreach ($invoices as $invoice) {
    $invoiceDate = DateTime::createFromFormat('d/m/Y', $invoice->getInvoiceDate());

    
    $month = $invoiceDate->format('m');
    $year = $invoiceDate->format('Y');
    $dateKey = $month . '-' . $year;


    $monthYear=$month.'-'.$year;

    if (!isset($statistics[$monthYear])) {
        $statistics[$monthYear] = [
            'monthYear' => $monthYear,
            'count' => 0,
            'totalTTC' => 0,
            'totalTVA' => 0,
            
        ];
    }

    
    $statistics[$monthYear]['count']++;
   
    $totalTTC = str_replace(',', '.', $invoice->getInvoiceAmountTtc());
    $totalTTC = preg_replace('/[^0-9.]/', '', $totalTTC); 
    $totalTTC = floatval($totalTTC); 
   

    $totalTVA = str_replace(',', '.', $invoice->getInvoiceTaxAmount());
    $totalTVA = preg_replace('/[^0-9.]/', '', $totalTVA); 
    $totalTVA = floatval($totalTVA); 
   

    $statistics[$monthYear]['totalTTC'] += $totalTTC;
    $statistics[$monthYear]['totalTVA'] += $totalTVA;


    $invoiceCount++;
    $totalTTC += $this->convertToFloat($invoice->getInvoiceAmountTtc());
    $totalTVA += $this->convertToFloat($invoice->getInvoiceTaxAmount());

}



usort($statistics, function ($a, $b) {
    $dateA = DateTime::createFromFormat('m-Y', $a['monthYear']);
    $dateB = DateTime::createFromFormat('m-Y', $b['monthYear']);
    return $dateB <=> $dateA;
});


        
        foreach ( $statistics as $result ) {
                $yearMonth = substr($result[ 'monthYear' ] ,0 , 2);

                $auxVar=$result[ 'monthYear' ];
                
                


                $month="";
                $year="";
                switch ( $yearMonth ) {
                    case '01' :  $result[ 'monthYear' ] = 'Janvier'.substr($result[ 'date' ] , 2 , 5); $month="01"; $year=substr($auxVar , 3,4);
                    break;
                    case '02' :   $result[ 'monthYear' ] = 'Février'.substr($result[ 'monthYear' ] , 2 , 5); $month="02"; $year=substr($auxVar  , 3,4);
                    break;
                    case '03' :   $result[ 'monthYear' ] = 'Mars'.substr($result[ 'monthYear' ] , 2 , 5); $month="03"; $year=substr($auxVar  , 3,4);
                    break;
                    case '04' :   $result[ 'monthYear' ] = 'Avril'.substr($result[ 'monthYear' ] , 2 , 5); $month="04";$year=substr($auxVar  , 3,4);
                    break;
                    case '05' :   $result[ 'monthYear' ] = 'Mai'.substr($result[ 'monthYear' ] , 2 , 5); $month="05";$year=substr($auxVar  , 3,4);
                    break;
                    case '06' :   $result[ 'monthYear' ] = 'Juin'.substr($result[ 'monthYear' ] , 2 , 5); $month="06";$year=substr($auxVar  , 3,4);
                    break;
                    case '07' :   $result[ 'monthYear' ] = 'Juillet'.substr($result[ 'monthYear' ] , 2 , 5); $month="07";$year=substr($auxVar  , 3,4);
                    break;
                    case '08' :   $result[ 'monthYear' ] = 'Août'.substr($result[ 'monthYear' ] , 2 , 5); $month="08";$year=substr($auxVar  , 3,4);
                    break;
                    case '09' :   $result[ 'monthYear' ] = 'Septembre'.substr($result[ 'monthYear' ] , 2 , 5); $month="09";$year=substr($auxVar  , 3,4);
                    break;
                    case '10' :   $result[ 'monthYear' ] = 'Octobre'.substr($result[ 'monthYear' ] , 2 , 5); $month="10";$year=substr($auxVar  , 3,4);
                    break;
                    case '11' :   $result[ 'monthYear' ] = 'Novembre'.substr($result[ 'monthYear' ] , 2 , 5); $month="11";$year=substr($auxVar  , 3,4);
                    break;
                    case '12' :   $result[ 'monthYear' ] = 'Décembre'.substr($result[ 'monthYear' ] , 2 , 5); $month="12";$year=substr($auxVar  , 3,4);
                    break;

                }

                


                $statsArray[]=[
                    'date' =>  $result[ 'monthYear' ],
                    'amount' => $result['totalTTC'],
                    'dateAux' => $month.'/'.$year,
                    'tva' => $result['totalTVA'],
                    'count' => $result['count'],

                ];

             
            

        }


        $startDate = DateTime::createFromFormat( 'd/m/Y', $startDate )->setTime( 0, 0, 0 );
        $endDate = DateTime::createFromFormat( 'd/m/Y', $endDate )->setTime( 23, 59, 59 );


        $filteredStatistics = [];
        foreach ( $statsArray as $stat ) {
            $date = DateTime::createFromFormat('d/m/Y', '01/' . $stat['dateAux']);


            if ( $date >= $startDate && $date <= $endDate ) {
                $filteredStatistics[] = $stat;
            }
        }



        return new JsonResponse( [
            'stats'=>array_reverse($filteredStatistics),
            'start' => $startDate,
            'end' => $endDate,

        ] ) ;

    }

    private function convertToFloat($value) {

        $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^0-9.]/', '', $value); 
    $value = floatval($value); 
   

       

        $cleanAmount = preg_replace('/[^0-9.]/', '', $value);

        return floatval($cleanAmount);
    }
}
