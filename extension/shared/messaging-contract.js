/**
 * MessagingContract
 *
 * Contrato previsto de comunicação entre a extensão do Collector369 e o
 * núcleo do Collector369 (PHP). Define apenas os tipos de mensagem
 * esperados nesta fundação; o mecanismo de transporte (native messaging,
 * servidor local, ou convenção de arquivo) e a lógica de coleta serão
 * definidos em sprints futuras.
 */

export const MESSAGE_TYPES = Object.freeze({
    COLLECTION_REQUESTED: 'collection_requested',
    COLLECTION_COMPLETED: 'collection_completed',
    COLLECTION_FAILED: 'collection_failed',
});
