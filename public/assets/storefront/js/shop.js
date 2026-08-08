(function ($) {
    "use strict";
 
    /* Filter Sort
    -------------------------------------------------------------------------------------*/
    var filterSort = function () {
        let isListActive = $(".sw-layout-list").hasClass("active");
        let originalProductsList = $("#listLayout .card-product").clone();
        let originalProductsGrid = $("#gridLayout .card-product").clone();
        let paginationList = $("#listLayout .wd-full").clone();
        let paginationGrid = $("#gridLayout .wd-full").clone();

        $(".select-item").on("click", function () {
            const sortValue = $(this).data("sort-value");
            $(".select-item").removeClass("active");
            $(this).addClass("active");
            $(".text-sort-value").text($(this).find(".text-value-item").text());

            applyFilter(sortValue, isListActive);
            $("#listLayout .wd-full").remove();
            $("#gridLayout .wd-full").remove();

            if ($(".meta-filter-shop").hasClass("active")) {
                $("#listLayout").append(paginationList.clone().css("display", "none"));
                $("#gridLayout").append(paginationGrid.clone().css("display", "none"));
            } else {
                $("#listLayout").append(paginationList.clone().css("display", "flex"));
                $("#gridLayout").append(paginationGrid.clone().css("display", "flex"));
            }

            if (!$(".meta-filter-shop").hasClass("active")) {
                limitLayout();
            }
        });

        $(".tf-view-layout-switch").on("click", function () {
            const layout = $(this).data("value-layout");

            if (layout === "list") {
                isListActive = true;
                $("#gridLayout").hide();
                $("#listLayout").show();
            } else {
                isListActive = false;
                $("#listLayout").hide();
                setGridLayout(layout);
            }
        });

        function applyFilter(sortValue, isListActive) {
            let products;

            if (isListActive) {
                products = $("#listLayout .card-product");
            } else {
                products = $("#gridLayout .card-product");
            }

            if (sortValue === "best-selling") {
                if (isListActive) {
                    $("#listLayout").empty().append(originalProductsList.clone());
                } else {
                    $("#gridLayout").empty().append(originalProductsGrid.clone());
                }
                bindProductEvents();
                limitLayout();
                return;
            }

            if (sortValue === "price-low-high") {
                products.sort(
                    (a, b) =>
                        parseFloat($(a).find(".price-new").text().replace("$", "")) - parseFloat($(b).find(".price-new").text().replace("$", ""))
                );
            } else if (sortValue === "price-high-low") {
                products.sort(
                    (a, b) =>
                        parseFloat($(b).find(".price-new").text().replace("$", "")) - parseFloat($(a).find(".price-new").text().replace("$", ""))
                );
            } else if (sortValue === "a-z") {
                products.sort((a, b) => $(a).find(".name-product").text().localeCompare($(b).find(".name-product").text()));
            } else if (sortValue === "z-a") {
                products.sort((a, b) => $(b).find(".name-product").text().localeCompare($(a).find(".name-product").text()));
            }

            if (isListActive) {
                $("#listLayout").empty().append(products);
            } else {
                $("#gridLayout").empty().append(products);
            }

            bindProductEvents();
        }

        function setGridLayout(layoutClass) {
            $("#gridLayout").show().removeClass().addClass(`wrapper-shop tf-grid-layout ${layoutClass}`);
            $(".tf-view-layout-switch").removeClass("active");
            $(`.tf-view-layout-switch[data-value-layout="${layoutClass}"]`).addClass("active");
        }
        function bindProductEvents() {
            if ($(".card-product").length > 0) {
                $(".color-swatch").on("click mouseover", function () {
                    var swatchColor = $(this).find("img").attr("src");
                    var imgProduct = $(this).closest(".card-product").find(".img-product");
                    imgProduct.attr("src", swatchColor);
                    $(this).closest(".card-product").find(".color-swatch.active").removeClass("active");
                    $(this).addClass("active");
                });
            }
            $(".size-box")
                .off("click", ".size-item")
                .on("click", ".size-item", function () {
                    $(this).closest(".size-box").find(".size-item").removeClass("active");
                    $(this).addClass("active");
                });
        }
        bindProductEvents();
    };

    /* Switch Layout 
    -------------------------------------------------------------------------------------*/
    var swLayoutShop = function () {
        let isListActive = $(".sw-layout-list").hasClass("active");
        let userSelectedLayout = null;

        function hasValidLayout() {
            return (
                $("#gridLayout").hasClass("tf-col-1") ||
                $("#gridLayout").hasClass("tf-col-2") ||
                $("#gridLayout").hasClass("tf-col-3") ||
                $("#gridLayout").hasClass("tf-col-4") ||
                $("#gridLayout").hasClass("tf-col-5") ||
                $("#gridLayout").hasClass("tf-col-6") ||
                $("#gridLayout").hasClass("tf-col-7")
            );
        }

        function updateLayoutDisplay() {
            const windowWidth = $(window).width();
            const currentLayout = $("#gridLayout").attr("class");

            if (!hasValidLayout()) {
                console.warn("Page does not contain a valid layout (2-7 columns), skipping layout adjustments.");
                return;
            }

            if (isListActive) {
                $("#gridLayout").hide();
                $("#listLayout").show();
                $(".wrapper-control-shop").addClass("listLayout-wrapper").removeClass("gridLayout-wrapper");
                return;
            }

            if (userSelectedLayout) {
                if (windowWidth <= 767) {
                    setGridLayout("tf-col-2");
                } else if (windowWidth <= 1200 && userSelectedLayout !== "tf-col-2") {
                    setGridLayout("tf-col-3");
                } else if (
                    windowWidth <= 1400 &&
                    (userSelectedLayout === "tf-col-5" || userSelectedLayout === "tf-col-6" || userSelectedLayout === "tf-col-7")
                ) {
                    setGridLayout("tf-col-4");
                } else {
                    setGridLayout(userSelectedLayout);
                }
                return;
            }
            if (windowWidth <= 767) {
                if (!currentLayout.includes("tf-col-2")) {
                    setGridLayout("tf-col-2");
                }
            } else if (windowWidth <= 1200) {
                if (!currentLayout.includes("tf-col-3")) {
                    setGridLayout("tf-col-3");
                }
            } else if (windowWidth <= 1401) {
                if (currentLayout.includes("tf-col-5") || currentLayout.includes("tf-col-6") || currentLayout.includes("tf-col-7")) {
                    setGridLayout("tf-col-4");
                }
            } else {
                $("#listLayout").hide();
                $("#gridLayout").show();
                $(".wrapper-control-shop").addClass("gridLayout-wrapper").removeClass("listLayout-wrapper");
            }
        }

        function setGridLayout(layoutClass) {
            $("#listLayout").hide();
            $("#gridLayout").show().removeClass().addClass(`wrapper-shop tf-grid-layout ${layoutClass}`);
            $(".tf-view-layout-switch").removeClass("active");
            $(`.tf-view-layout-switch[data-value-layout="${layoutClass}"]`).addClass("active");
            $(".wrapper-control-shop").addClass("gridLayout-wrapper").removeClass("listLayout-wrapper");
            isListActive = false;
        }

        $(document).ready(function () {
            if (isListActive) {
                $("#gridLayout").hide();
                $("#listLayout").show();
                $(".wrapper-control-shop").addClass("listLayout-wrapper").removeClass("gridLayout-wrapper");
            } else {
                $("#listLayout").hide();
                $("#gridLayout").show();
                updateLayoutDisplay();
            }
        });

        $(window).on("resize", () => {
            updateLayoutDisplay();

            if ($(".meta-filter-shop").hasClass("active") == false) {
                limitLayout();
            }
        });

        $(".tf-view-layout-switch").on("click", function () {
            const layout = $(this).data("value-layout");
            $(".tf-view-layout-switch").removeClass("active");
            $(this).addClass("active");
            $(".wrapper-control-shop").addClass("loading-shop");
            setTimeout(() => {
                $(".wrapper-control-shop").removeClass("loading-shop");
                if (isListActive) {
                    $("#gridLayout").css("display", "none");
                    $("#listLayout").css("display", "");
                } else {
                    $("#listLayout").css("display", "none");
                    $("#gridLayout").css("display", "");
                    if ($(".meta-filter-shop").hasClass("active") == false) {
                        limitLayout();
                    }
                }
            }, 500);

            if (layout === "list") {
                isListActive = true;
                userSelectedLayout = null;
                $("#gridLayout").hide();
                $("#listLayout").show();
                $(".wrapper-control-shop").addClass("listLayout-wrapper").removeClass("gridLayout-wrapper");
            } else {
                userSelectedLayout = layout;
                setGridLayout(layout);
            }
        });
    };

    /* Loading product 
    -------------------------------------------------------------------------------------*/
    var loadProduct = function () {
        const gridInitialItems = 8;
        const listInitialItems = 4;
        const gridItemsPerPage = 4;
        const listItemsPerPage = 2;

        let listItemsDisplayed = listInitialItems;
        let gridItemsDisplayed = gridInitialItems;
        let scrollTimeout;

        function hideExtraItems(layout, itemsDisplayed) {
            layout.find(".loadItem").each(function (index) {
                if (index >= itemsDisplayed) {
                    $(this).addClass("hidden");
                }
            });
            if (layout.is("#listLayout")) updateLastVisible(layout);
        }

        function showMoreItems(layout, itemsPerPage, itemsDisplayed) {
            const hiddenItems = layout.find(".loadItem.hidden");

            setTimeout(function () {
                hiddenItems.slice(0, itemsPerPage).removeClass("hidden");
                if (layout.is("#listLayout")) updateLastVisible(layout);
                checkLoadMoreButton(layout);
            }, 600);

            return itemsDisplayed + itemsPerPage;
        }

        function updateLastVisible(layout) {
            layout.find(".loadItem").removeClass("last-visible");
            layout.find(".loadItem").not(".hidden").last().addClass("last-visible");
        }
        function checkLoadMoreButton(layout) {
            if (layout.find(".loadItem.hidden").length === 0) {
                if (layout.is("#listLayout")) {
                    $("#loadMoreListBtn").hide();
                    $("#infiniteScrollList").hide();
                } else if (layout.is("#gridLayout")) {
                    $("#loadMoreGridBtn").hide();
                    $("#infiniteScrollGrid").hide();
                }
            }
        }

        hideExtraItems($("#listLayout"), listItemsDisplayed);
        hideExtraItems($("#gridLayout"), gridItemsDisplayed);

        $("#loadMoreListBtn").on("click", function () {
            listItemsDisplayed = showMoreItems($("#listLayout"), listItemsPerPage, listItemsDisplayed);
        });

        $("#loadMoreGridBtn").on("click", function () {
            gridItemsDisplayed = showMoreItems($("#gridLayout"), gridItemsPerPage, gridItemsDisplayed);
        });

        function onScroll() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function () {
                const infiniteScrollList = $("#infiniteScrollList");
                const infiniteScrollGrid = $("#infiniteScrollGrid");

                if (infiniteScrollList.is(":visible") && isElementInViewport(infiniteScrollList)) {
                    listItemsDisplayed = showMoreItems($("#listLayout"), listItemsPerPage, listItemsDisplayed);
                }

                if (infiniteScrollGrid.is(":visible") && isElementInViewport(infiniteScrollGrid)) {
                    gridItemsDisplayed = showMoreItems($("#gridLayout"), gridItemsPerPage, gridItemsDisplayed);
                }
            }, 300);
        }
        function isElementInViewport(el) {
            const rect = el[0].getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        $(window).on("scroll", onScroll);
    };

    /* Handle Dropdown Filter 
    -------------------------------------------------------------------------------------*/
    var handleDropdownFilter = function () {
        if ($(".wrapper-filter-dropdown").length > 0) {
            $(".btn-filterDropdown").on("click", function (event) {
                event.stopPropagation();
                $(".filter-drawer-wrap").toggleClass("show");
                $(this).toggleClass("active");
                var icon = $(this).find(".icon");
                if ($(this).hasClass("active")) {
                    icon.removeClass("icon-filter").addClass("icon-X2");
                } else {
                    icon.removeClass("icon-X2").addClass("icon-filter");
                }
                if ($(window).width() <= 1199) {
                    $(".overlay-filter").addClass("show");
                }
            });
            $(document).on("click", function (event) {
                if (!$(event.target).closest(".wrapper-filter-dropdown").length) {
                    $(".filter-drawer-wrap").removeClass("show");
                    $(".btn-filterDropdown").removeClass("active");
                    $(".btn-filterDropdown .icon").removeClass("icon-X2").addClass("icon-filter");
                }
            });
            $(".close-filter ,.overlay-filter").on("click", function () {
                $(".filter-drawer-wrap").removeClass("show");
                $(".btn-filterDropdown").removeClass("active");
                $(".btn-filterDropdown .icon").removeClass("icon-X2").addClass("icon-filter");
                $(".overlay-filter").removeClass("show");
            });
        }
    };

    /* Limit Layout
    -------------------------------------------------------------------------------------*/
    function limitLayout() {
        const $gridLayout = $("#gridLayout");
        const $listLayout = $("#listLayout");
        const gridItems = $("#gridLayout .card-product");
        const listItems = $("#listLayout .card-product");
        const layoutClassGrid = $gridLayout.attr("class") || "";
        const $btn = $("#loadMoreBtn");

        let maxItems = 0;
        let initItem = 0;
        let maxItemList = 5;

        if (layoutClassGrid.includes("tf-grid-1")) {
            maxItems = 3; initItem = 1;
        } else if (layoutClassGrid.includes("tf-col-2")) {
            maxItems = 6; initItem = 2;
        } else if (layoutClassGrid.includes("tf-col-3")) {
            maxItems = 9; initItem = 3;
        } else if (layoutClassGrid.includes("tf-col-4")) {
            maxItems = 8; initItem = 4;
        } else if (layoutClassGrid.includes("tf-col-5")) {
            maxItems = 15; initItem = 5;
        } else if (layoutClassGrid.includes("tf-col-6")) {
            maxItems = 18; initItem = 6;
        } else if (layoutClassGrid.includes("tf-col-7")) {
            maxItems = 21; initItem = 7;
        }

        function renderGrid() {
            gridItems.each(function (index) {
                if (index < maxItems) $(this).css("display", "flex");
                else $(this).hide();
            });

            const $wdFullGrid = $gridLayout.find(".wd-full");
            if (gridItems.length <= maxItems) $wdFullGrid.hide();
            else $wdFullGrid.css("display", "flex");
        }

        function renderList() {
            listItems.each(function (index) {
                if (index < maxItemList) $(this).css("display", "flex");
                else $(this).hide();
            });
        }

        function isElementAtHalfViewport($el) {
            if (!$el.length || !$el.is(":visible")) return false;

            const rect = $el[0].getBoundingClientRect();
            const vh = window.innerHeight || document.documentElement.clientHeight;

            return rect.top <= vh * 0.95;
        }


        let isLoading = false;

        function loadMore() {
            if (isLoading) return;
            if (gridItems.length <= maxItems) return;

            isLoading = true;
            $btn.addClass("loading").prop("disabled", true);

            setTimeout(function () {
                maxItems += initItem;
                renderGrid();

                isLoading = false;
                $btn.removeClass("loading").prop("disabled", false);

                if ($btn.hasClass("infinite-scroll") && gridItems.length > maxItems) {
                    if (isElementAtHalfViewport($btn)) {
                        loadMore();
                    }

                }
            }, 500);
        }

        renderGrid();
        renderList();

        $btn.off("click.limitLayout").on("click.limitLayout", function () {
            loadMore();
        });

        $(window).off("scroll.limitLayout").on("scroll.limitLayout", function () {
            if (!$btn.hasClass("infinite-scroll")) return;

            if (!$gridLayout.is(":visible")) return;

            if (isElementAtHalfViewport($btn)) {
                loadMore();
            }

        });

        if ($btn.hasClass("infinite-scroll")) {
            $(window).trigger("scroll.limitLayout");
        }
    }


    $(function () {
        
        filterSort();
        loadProduct();
        handleDropdownFilter();
        swLayoutShop();
        limitLayout();
    });
})(jQuery);
