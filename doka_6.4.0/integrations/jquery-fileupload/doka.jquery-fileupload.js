function useDokaWithJQueryFileUpload(Doka, options) {

    // Create a default Doka editor
    var doka = Doka.create(options);

    // add Doka processor to queue
    $.blueimp.fileupload.prototype.options.processQueue.push({ action: 'doka' });

    // Define Doka file processor
    $.widget('blueimp.fileupload', $.blueimp.fileupload, {
        processActions: {
            doka: function(data) {

                var dfd = $.Deferred();
                var file = data.files[data.index];
                var _this = this;

                doka.edit(file).then(function(output) {

                    // no output
                    if (!output) {
                        file.error = 'Not edited';
                        data.files.error = true;
                        dfd.rejectWith(this, [data]);
                        return;
                    }
                    
                    // no thumnail needed?
                    if (!data.previewThumbnail) {
                        data.files[data.index] = output.file;
                        return dfd.resolveWith(_this, [data]);
                    }

                    // create new thumbnail
                    var image = new Image();
                    image.onload = function() {

                        var x = 0;
                        var y = 0;
                        var width = image.width;
                        var height = image.height;
                        var previewWidth = data.previewMaxWidth;
                        var previewHeight = data.previewMaxHeight;
                        var scalar = Math.min(previewWidth / width, previewHeight / height);
                        
                        if (data.previewCrop) {
                        
                            var previewAspectRatio = previewHeight / previewWidth
                            var imageAspectRatio = height / width;
                            
                            if (previewAspectRatio < imageAspectRatio) {
                                y = (height - width) * .5;
                                height = width;
                            }
                            else if (previewAspectRatio > imageAspectRatio) {
                                x = (width - height) * .5;
                                width = height;
                            }
                        }
                        else {
                            previewWidth = width * scalar;
                            previewHeight = height * scalar;
                        }

                        var preview = document.createElement('canvas');
                        preview.width = previewWidth;
                        preview.height = previewHeight;
                        var ctx = preview.getContext('2d');
                        ctx.drawImage(image, 
                            x, y, width, height,
                            0, 0, previewWidth, previewHeight
                        );
                        
                        output.file.preview = preview;
                        data.files[data.index] = output.file;
                        return dfd.resolveWith(_this, [data]);
                    }

                    // start loading the outputted image
                    image.src = URL.createObjectURL(output.file);
                });

                return dfd.promise();
            }
        }
    });
};