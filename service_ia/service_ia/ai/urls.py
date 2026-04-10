from django.urls import path
from . import views

urlpatterns = [
    path('', views.ia_view),
    path('chat/', views.chat_view),
]
