{{--
    Injected at TOPBAR_LOGO_BEFORE — a sibling of .fi-topbar-collapse-sidebar-btn-ctn
    inside .fi-topbar-start. Removes that container immediately (no tick delay needed
    because it appears above this element in document order) and keeps it removed via
    a MutationObserver scoped only to the .fi-topbar-start element.
--}}
<span
    x-data="{}"
    x-init="
        (function (parent) {
            function removeButtons() {
                var ctn = parent.querySelector('.fi-topbar-collapse-sidebar-btn-ctn');
                if (ctn) ctn.remove();
            }
            removeButtons();
            new MutationObserver(removeButtons).observe(parent, { childList: true, subtree: false });
        })($el.parentElement)
    "
></span>
