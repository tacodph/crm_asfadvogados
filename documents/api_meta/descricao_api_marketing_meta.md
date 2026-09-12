O portal central que concentra toda a documentação oficial, referências de endpoints e guias passo a passo para desenvolvedores é o Meta for Developers.

Os links diretos específicos para as duas frentes da API da Meta são:

- Documentação da API de Marketing (MAPI)
Se você também for automatizar a criação de campanhas, gerenciamento de anúncios, orçamentos ou extração de relatórios de métricas do Meta Ads diretamente no seu framework:
- Visão Geral da Marketing API: https://developers.facebook.com/documentation/ads-commerce/marketing-api/overview 
- Referência Completa de Endpoints da Marketing API: https://developers.facebook.com/documentation/ads-commerce/marketing-api/overview

- Dica para Testes na Documentação

Ao acessar os links acima com a sua conta do Facebook conectada, você terá acesso ao Payload Helper e ao Graph API Explorer da Meta dentro do próprio portal. Essas ferramentas geram simulações em tempo real do JSON exato que o seu código Laravel precisa enviar, o que facilita muito a validação dos parâmetros antes de escrever a lógica final.

- Recomendações Importantes para Laravel

- Deduplicação (event_id): O parâmetro event_id enviado no exemplo ('order_' . $order->id) deve ser idêntico ao event_id enviado pelo Pixel do navegador (JavaScript). Se os dois IDs forem iguais, a Meta descarta o evento duplicado e mantém o rastreamento perfeito.
- Uso de Filas (Queues): O envio de requisições externas HTTP pode atrasar o carregamento da página para o seu cliente. O ideal é transformar o envio em um Job em segundo plano (php artisan make:job SendMetaCapiEvent) utilizando as Filas do Laravel para garantir performance máxima.