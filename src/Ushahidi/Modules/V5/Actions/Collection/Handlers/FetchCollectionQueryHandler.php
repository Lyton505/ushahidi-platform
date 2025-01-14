<?php

namespace Ushahidi\Modules\V5\Actions\Collection\Handlers;

use App\Bus\Query\AbstractQueryHandler;
use App\Bus\Query\Query;
use Ushahidi\Core\Tool\Authorizer\SetAuthorizer;
use Ushahidi\Modules\V5\Actions\Collection\Queries\FetchCollectionQuery;
use Ushahidi\Modules\V5\Repository\Set\SetRepository as CollectionRepository;
use Ushahidi\Core\Tool\SearchData;
use Illuminate\Support\Facades\Auth;

use function JmesPath\search;

class FetchCollectionQueryHandler extends AbstractQueryHandler
{
    private $collection_repository;

    /**
     * @var SetAuthorizer
     */
    private $setAuthorizer;

    public function __construct(
        CollectionRepository $collection_repository,
        SetAuthorizer $setAuthorizer
    ) {
        $this->collection_repository = $collection_repository;
        $this->setAuthorizer = $setAuthorizer;
    }

    protected function isSupported(Query $query)
    {
        assert(
            get_class($query) === FetchCollectionQuery::class,
            'Provided query is not supported'
        );
    }

    /**
     * @param FetchCollectionQuery $action
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * phpcs:disable
     */
    public function __invoke($query) //: LengthAwarePaginator
    {
        // TODO: This is redundant, we should be able to remove this
        $this->isSupported($query);

        $search_fields = $query->getSearchData();

        $search = new SearchData();

        // TODO: Move this to the Query class
        $user = Auth::guard()->user();

        $search->setFilter('is_saved_search', false);
        $search->setFilter('with_post_count', true);

        // Querying Values
        $search->setFilter('keyword', $search_fields->q());
        $search->setFilter('role', $search_fields->role());
        $search->setFilter('is_admin', $search_fields->role() === "admin");
        $search->setFilter('user_id', $user->id ?? null);

        // Paging Values
        $limit = $query->getLimit();

        $search->setFilter('limit', $limit);
        $search->setFilter('skip', $limit * ($query->getPage() - 1));

        // Sorting Values
        $search->setFilter('sort', $query->getSortBy());
        $search->setFilter('order', $query->getOrder());

        $this->collection_repository->setSearchParams($search);

        // TODO: We shouldn't let the repository return a Laravel paginator instance,
        // this should be created in the controller
        $result = $this->collection_repository->fetch();

        return $result;
    }
}
