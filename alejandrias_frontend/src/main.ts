import { bootstrapApplication } from '@angular/platform-browser';
import { App } from './app/app.component';

// 🔥 IMPORTANTE
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { authInterceptor } from './app/interceptors/auth-interceptor';
import { provideRouter } from '@angular/router';
import { routes } from '@app/app.routes';

bootstrapApplication(App, {
  providers: [
    provideHttpClient(withInterceptors([authInterceptor])),
    provideRouter(routes)
  ]
});