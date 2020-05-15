import { BrowserModule } from '@angular/platform-browser';
import { NgModule } from '@angular/core';

import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.esm';
import FilePondPluginImageExifOrientation from 'filepond-plugin-image-exif-orientation/dist/filepond-plugin-image-exif-orientation.esm';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.esm';
import FilePondPluginImageEdit from 'filepond-plugin-image-edit/dist/filepond-plugin-image-edit.esm';
import FilePondPluginImageCrop from 'filepond-plugin-image-crop/dist/filepond-plugin-image-crop.esm';
import FilePondPluginImageResize from 'filepond-plugin-image-resize/dist/filepond-plugin-image-resize.esm';
import FilePondPluginImageTransform from 'filepond-plugin-image-transform/dist/filepond-plugin-image-transform.esm';

import { FilePondModule, registerPlugin } from 'ngx-filepond';

registerPlugin(
  FilePondPluginFileValidateType,
  FilePondPluginImageExifOrientation,
  FilePondPluginImageCrop,
  FilePondPluginImageResize,
  FilePondPluginImagePreview,
  FilePondPluginImageEdit,
  FilePondPluginImageTransform
);

import { AppComponent } from './app.component';

import { AngularDokaModule } from 'angular-doka';
import { DemoInlineComponent } from './demo/demo-inline.component';
import { DemoModalComponent } from './demo/demo-modal.component';
import { DemoPreviewComponent } from './demo/demo-preview.component';
import { DemoFilePondComponent } from './demo/demo-filepond.component';
import { DemoProfileComponent } from './demo/demo-profile.component';

@NgModule({
  declarations: [
    AppComponent,
    DemoInlineComponent,
    DemoModalComponent,
    DemoPreviewComponent,
    DemoFilePondComponent,
    DemoProfileComponent
  ],
  imports: [
    AngularDokaModule,
    BrowserModule,
    FilePondModule
  ],
  providers: [],
  bootstrap: [AppComponent]
})
export class AppModule { }
