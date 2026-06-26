import os


class SimpleCorsMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response
        self.allowed_origins = {
            origin.strip()
            for origin in os.getenv(
                "CORS_ALLOWED_ORIGINS",
                "http://localhost:4200,http://127.0.0.1:4200",
            ).split(",")
            if origin.strip()
        }

    def __call__(self, request):
        if request.method == "OPTIONS":
            response = self._build_preflight_response()
        else:
            response = self.get_response(request)

        return self._add_cors_headers(request, response)

    def _build_preflight_response(self):
        from django.http import HttpResponse

        return HttpResponse(status=200)

    def _add_cors_headers(self, request, response):
        request_origin = request.headers.get("Origin")

        if request_origin in self.allowed_origins:
            response["Access-Control-Allow-Origin"] = request_origin

        response["Access-Control-Allow-Headers"] = "Authorization, Content-Type"
        response["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
        response["Access-Control-Allow-Credentials"] = "true"
        return response
