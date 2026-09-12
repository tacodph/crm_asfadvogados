O portal central que concentra toda a documentação oficial, referências de endpoints e guias passo a passo para desenvolvedores é o Meta for Developers.

Os links diretos específicos para as duas frentes da API da Meta são:

1. Documentação da API de Conversões (CAPI)

Se o seu foco atual é o rastreamento, envio de eventos do lado do servidor (como o evento Purchase que estruturamos no Laravel) e parâmetros de correspondência avançada:
- Documentação de Implementação Ponta a Ponta da API de Conversões: https://developers.facebook.com/documentation/ads-commerce/conversions-api/guides/end-to-end-implementation 
- Guia de Início Rápido (Get Started): https://developers.facebook.com/documentation/ads-commerce/conversions-api/guides/end-to-end-implementation

A API de Conversões da Meta (CAPI) é uma ferramenta que conecta os dados do seu servidor, site ou CRM diretamente aos sistemas de anúncios da Meta.

- O que é e para que serve? 
A API de Conversões envia ações dos clientes — como cliques, cadastros ou compras — do seu próprio servidor para o Facebook.
a. Substitui ou complementa o Pixel: Funciona junto com o Pixel tradicional para evitar a perda de dados causada por bloqueadores de anúncios ou restrições de privacidade.
b.Melhora os resultados: Ajuda a reduzir o custo por resultado e otimizar a entrega dos seus anúncios com base em dados mais precisos da jornada do cliente.

- Como configurar?
Você pode ativar a API de Conversões de diferentes maneiras, dependendo da plataforma onde seu negócio está hospedado. Utilize o Gateway da API para uma implementação rápida baseada em nuvem sem precisar de códigos complexos.

- Links Sobre a API de Conversões: 
    a. https://pt-br.facebook.com/business/help/2041148702652965?id=818859032317965
    b. https://pt-br.facebook.com/business/tools/conversions-api

- Dica para Testes na Documentação

Ao acessar os links acima com a sua conta do Facebook conectada, você terá acesso ao Payload Helper e ao Graph API Explorer da Meta dentro do próprio portal. Essas ferramentas geram simulações em tempo real do JSON exato que o seu código Laravel precisa enviar, o que facilita muito a validação dos parâmetros antes de escrever a lógica final.

- Recomendações Importantes para Laravel

- Deduplicação (event_id): O parâmetro event_id enviado no exemplo ('order_' . $order->id) deve ser idêntico ao event_id enviado pelo Pixel do navegador (JavaScript). Se os dois IDs forem iguais, a Meta descarta o evento duplicado e mantém o rastreamento perfeito.
- Uso de Filas (Queues): O envio de requisições externas HTTP pode atrasar o carregamento da página para o seu cliente. O ideal é transformar o envio em um Job em segundo plano (php artisan make:job SendMetaCapiEvent) utilizando as Filas do Laravel para garantir performance máxima.
