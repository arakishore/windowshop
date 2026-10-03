window.orderProductGalleryImages = function (images, selectedValueId) {
    if (!Array.isArray(images)) return [];

    var preferredImages = images.filter(function (image) {
        return Array.isArray(image.attribute_value_ids) && image.attribute_value_ids.includes(selectedValueId);
    });

    if (preferredImages.length === 0) {
        preferredImages = images.filter(function (image) {
            return Array.isArray(image.attribute_value_ids) && image.attribute_value_ids.length === 0;
        });
    }

    var preferredIds = new Set(preferredImages.map(function (image) {
        return image.id;
    }));

    return preferredImages.concat(images.filter(function (image) {
        return !preferredIds.has(image.id);
    }));
};
