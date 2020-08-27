<?php declare(strict_types=1);

namespace Popov\FastOrder\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Storefront\Page\GenericPageLoader;

class FastOrderController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductLineItemFactory
     */
    private $productLineItemFactory;

    /**
     * @var GenericPageLoader
     */
    private $genericPageLoader;

    public function __construct(
        CartService $cartService,
        SalesChannelRepositoryInterface $productRepository,
        ProductLineItemFactory $productLineItemFactory,
        GenericPageLoader $genericPageLoader
    ) {
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
        $this->productLineItemFactory = $productLineItemFactory;
        $this->genericPageLoader = $genericPageLoader;
    }


    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/fast_order", name="fast-order.show", methods={"GET"})
     */
    public function show(Request $request, SalesChannelContext $context)
    {
        $page = $this->genericPageLoader->load($request, $context);
        return $this->renderStorefront('storefront/page/fast-order/index.html.twig', compact('page'));
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/fast_order", name="fast-order.store", options={"seo"="false"}, methods={"POST"})
     */
    public function store(Request $request, SalesChannelContext $context)
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $errorNumbers = [];

        for ($i = 1; $i <= 10; $i++) {

            $number = trim($request->request->get("number_{$i}"));

            if (empty($number)) {
                continue;
            }

            $quantity = $request->request->get("quantity_{$i}") > 0
                ? $request->request->get("quantity_{$i}")
                : 1;

            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('productNumber', $number));

            $idSearchResult = $this->productRepository->searchIds($criteria, $context);
            $data = $idSearchResult->getIds();

            if (empty($data)) {
                $errorNumbers[] = $number;
                continue;
            }

            $productId = array_shift($data);

            $product = $this->productLineItemFactory->create($productId, compact('quantity'));

            $cart = $this->cartService->add($cart, $product, $context);
        }

        if (!empty($errorNumbers)) {
            foreach ($errorNumbers as $errorNumber) {
                $this->addFlash(
                    'danger',
                    $this->trans('error.productNotFound', ['%number%' => $errorNumber])
                );
            }
        }

        return $this->forwardToRoute('frontend.checkout.cart.page');
    }
}
