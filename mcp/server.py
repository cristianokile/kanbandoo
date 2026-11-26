#!/usr/bin/env python3
"""
KanbanDoo MCP Server (JSON-RPC 2.0 over stdio — zero dependências externas).

Conecta qualquer agente compatível com MCP (Antigravity, Claude Code, Cursor)
à API REST do KanbanDoo para ler, criar, atualizar e concluir Tarefas e Notas.

Variáveis de ambiente:
  KANBANDOO_API_URL   (padrão: https://kile.com.br/tools/kanbandoo/api/agent)
  KANBANDOO_API_TOKEN (token pessoal kd_... gerado em Meu Perfil no KanbanDoo)
"""

import json
import os
import ssl
import sys
import urllib.error
import urllib.request
from typing import Any, Dict, List, Optional

for stream in (sys.stdin, sys.stdout, sys.stderr):
    try:
        stream.reconfigure(encoding="utf-8")
    except Exception:
        pass

API_URL = os.getenv("KANBANDOO_API_URL", "https://kile.com.br/tools/kanbandoo/api/agent").rstrip("/")
API_TOKEN = os.getenv("KANBANDOO_API_TOKEN", "").strip()

PROTOCOL_VERSION = "2024-11-05"
SERVER_INFO = {"name": "kanbandoo", "version": "1.0.0"}


def call_kanbandoo_api(action: str, payload: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    if not API_TOKEN:
        raise RuntimeError(
            "KANBANDOO_API_TOKEN não configurado. Defina a variável de ambiente com seu token de Meu Perfil no KanbanDoo."
        )

    body_dict = dict(payload or {})
    body_dict["action"] = action
    raw_data = json.dumps(body_dict, ensure_ascii=False).encode("utf-8")

    headers = {
        "Content-Type": "application/json; charset=utf-8",
        "Accept": "application/json",
        "Authorization": f"Bearer {API_TOKEN}",
        "X-API-Key": API_TOKEN,
        "User-Agent": "KanbanDoo-MCP/1.0",
    }

    req = urllib.request.Request(API_URL, data=raw_data, headers=headers, method="POST")
    ctx = ssl.create_default_context()

    try:
        with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
            text = resp.read().decode("utf-8", errors="replace")
            return json.loads(text)
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8", errors="replace")
        try:
            parsed = json.loads(err_body)
            return {"ok": False, "http_status": e.code, "error": parsed.get("error", err_body)}
        except Exception:
            return {"ok": False, "http_status": e.code, "error": err_body or str(e)}
    except Exception as e:
        return {"ok": False, "error": f"Falha de conexão com o KanbanDoo: {e}"}


TOOLS: List[Dict[str, Any]] = [
    {
        "name": "kanbandoo_whoami",
        "description": "Verifica a conexão com o KanbanDoo, retorna o usuário autenticado e um resumo das tarefas abertas, de hoje, atrasadas e total de notas.",
        "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
    },
    {
        "name": "kanbandoo_list_stages",
        "description": "Lista todas as colunas (estágios) do quadro Kanban com id, nome e slug.",
        "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
    },
    {
        "name": "kanbandoo_list_clients",
        "description": "Lista empresas/clientes ativos cadastrados no KanbanDoo (id, nome, categoria, resumo e links).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "search": {"type": "string", "description": "Filtro opcional por nome da empresa."}
            },
        },
    },
    {
        "name": "kanbandoo_list_team",
        "description": "Lista todos os membros da equipe cadastrados no KanbanDoo.",
        "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
    },
    {
        "name": "kanbandoo_list_tasks",
        "description": "Lista tarefas do KanbanDoo com filtros flexíveis (prazo, coluna, cliente, responsável, prioridade, busca textual). Por padrão retorna apenas tarefas em aberto.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "due_filter": {
                    "type": "string",
                    "enum": ["all", "today", "overdue", "week", "upcoming"],
                    "description": "Filtro rápido de prazo: today (hoje), overdue (atrasadas), week (próximos 7 dias), upcoming (hoje em diante), all (todas).",
                },
                "due_date": {"type": "string", "description": "Data exata de prazo (YYYY-MM-DD)."},
                "due_from": {"type": "string", "description": "Data inicial de prazo (YYYY-MM-DD)."},
                "due_to": {"type": "string", "description": "Data final de prazo (YYYY-MM-DD)."},
                "stage_slug": {"type": "string", "description": "Slug ou nome da coluna (ex: todo, doing, review, done)."},
                "stage_id": {"type": "integer", "description": "ID da coluna do Kanban."},
                "client_name": {"type": "string", "description": "Nome (ou parte do nome) do cliente."},
                "client_id": {"type": "integer", "description": "ID do cliente."},
                "only_mine": {"type": "boolean", "description": "Se true, retorna apenas tarefas atribuídas ao usuário do token."},
                "assignee_id": {"type": "integer", "description": "ID de um responsável específico."},
                "priority": {"type": "string", "enum": ["low", "medium", "high", "urgent"]},
                "search": {"type": "string", "description": "Busca textual no título, descrição ou nome do cliente."},
                "include_completed": {"type": "boolean", "description": "Incluir tarefas já concluídas (padrão: false)."},
                "limit": {"type": "integer", "description": "Máximo de resultados (padrão: 50, máx: 200)."},
            },
        },
    },
    {
        "name": "kanbandoo_get_task",
        "description": "Retorna todos os detalhes de uma tarefa (descrição completa, subtarefas/checklist, comentários, responsáveis e notas recentes do cliente vinculado).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID numérico da tarefa."},
                "include_client_notes": {
                    "type": "boolean",
                    "description": "Incluir notas recentes/fixadas do cliente vinculado para dar contexto (padrão: true).",
                },
            },
            "required": ["task_id"],
        },
    },
    {
        "name": "kanbandoo_create_task",
        "description": "Cria uma nova tarefa (ou evento com horário) no KanbanDoo, podendo já incluir cliente, coluna, prazo, tempo estimado, etiquetas e subtarefas.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "title": {"type": "string", "description": "Título da tarefa (obrigatório)."},
                "description": {"type": "string", "description": "Descrição detalhada / briefing da tarefa."},
                "client_name": {"type": "string", "description": "Nome do cliente (resolvido automaticamente)."},
                "client_id": {"type": "integer", "description": "ID do cliente (opcional se informar client_name)."},
                "stage_slug": {"type": "string", "description": "Slug ou nome da coluna inicial (padrão: primeira coluna / A Fazer)."},
                "stage_id": {"type": "integer", "description": "ID da coluna inicial."},
                "priority": {"type": "string", "enum": ["low", "medium", "high", "urgent"], "description": "Prioridade (padrão: medium)."},
                "due_date": {"type": "string", "description": "Data de entrega (YYYY-MM-DD)."},
                "due_time": {"type": "string", "description": "Horário agendado (HH:MM), ex: 14:30."},
                "estimated_minutes": {"type": "integer", "description": "Tempo estimado em minutos (ex: 30, 60)."},
                "assignee_ids": {"type": "array", "items": {"type": "integer"}, "description": "IDs dos responsáveis (padrão: usuário do token)."},
                "tags": {"type": "array", "items": {"type": "string"}, "description": "Lista de etiquetas."},
                "subtasks": {"type": "array", "items": {"type": "string"}, "description": "Lista de subtarefas (checklist) a criar junto."},
                "is_private": {"type": "boolean", "description": "Se true, marca como tarefa particular."},
            },
            "required": ["title"],
        },
    },
    {
        "name": "kanbandoo_update_task",
        "description": "Atualiza campos de uma tarefa existente (título, descrição, prazo, horário, prioridade, cliente, coluna, tempo estimado, etiquetas).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID da tarefa."},
                "title": {"type": "string"},
                "description": {"type": "string"},
                "priority": {"type": "string", "enum": ["low", "medium", "high", "urgent"]},
                "due_date": {"type": "string", "description": "YYYY-MM-DD ou vazio para remover."},
                "due_time": {"type": "string", "description": "HH:MM ou vazio para remover."},
                "estimated_minutes": {"type": "integer"},
                "client_id": {"type": "integer"},
                "client_name": {"type": "string"},
                "stage_id": {"type": "integer"},
                "stage_slug": {"type": "string"},
                "tags": {"type": "array", "items": {"type": "string"}},
                "is_private": {"type": "boolean"},
            },
            "required": ["task_id"],
        },
    },
    {
        "name": "kanbandoo_move_task",
        "description": "Move uma tarefa para outra coluna do Kanban (ex: doing, review, done).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID da tarefa."},
                "stage_slug": {"type": "string", "description": "Slug ou nome da coluna de destino (ex: doing, review, done)."},
                "stage_id": {"type": "integer", "description": "ID da coluna de destino."},
            },
            "required": ["task_id"],
        },
    },
    {
        "name": "kanbandoo_complete_task",
        "description": "Marca uma tarefa como concluída, move para a coluna Concluído e opcionalmente registra um comentário final com o resumo da entrega.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID da tarefa."},
                "comment": {"type": "string", "description": "Comentário opcional de conclusão/entrega."},
            },
            "required": ["task_id"],
        },
    },
    {
        "name": "kanbandoo_add_comment",
        "description": "Adiciona um comentário em uma tarefa (ideal para o agente registrar progresso, entregas, links ou resultados).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID da tarefa."},
                "content": {"type": "string", "description": "Texto do comentário."},
            },
            "required": ["task_id", "content"],
        },
    },
    {
        "name": "kanbandoo_add_subtask",
        "description": "Adiciona um novo item de checklist (subtarefa) a uma tarefa existente.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID da tarefa."},
                "title": {"type": "string", "description": "Título da subtarefa."},
            },
            "required": ["task_id", "title"],
        },
    },
    {
        "name": "kanbandoo_toggle_subtask",
        "description": "Marca ou desmarca uma subtarefa (item de checklist) como concluída.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "subtask_id": {"type": "integer", "description": "ID da subtarefa."},
                "is_completed": {"type": "boolean", "description": "true para concluída, false para pendente."},
            },
            "required": ["subtask_id", "is_completed"],
        },
    },
    {
        "name": "kanbandoo_delete_task",
        "description": "Move uma tarefa para a lixeira do KanbanDoo (ação reversível pela lixeira).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "task_id": {"type": "integer", "description": "ID da tarefa."},
            },
            "required": ["task_id"],
        },
    },
    {
        "name": "kanbandoo_list_note_groups",
        "description": "Lista os grupos/pastas de Notas & Conhecimento do KanbanDoo (ex: Atas de Reuniões, Diretrizes, Acessos).",
        "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
    },
    {
        "name": "kanbandoo_create_note_group",
        "description": "Cria um novo grupo/pasta de notas no KanbanDoo.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "name": {"type": "string", "description": "Nome do grupo."},
                "client_name": {"type": "string", "description": "Nome opcional do cliente vinculado."},
                "client_id": {"type": "integer", "description": "ID opcional do cliente vinculado."},
                "color": {"type": "string", "description": "Cor hexadecimal (ex: #6366f1)."},
            },
            "required": ["name"],
        },
    },
    {
        "name": "kanbandoo_list_notes",
        "description": "Lista e pesquisa notas da base de conhecimento do KanbanDoo por cliente, grupo, período ou palavra-chave.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "client_name": {"type": "string", "description": "Nome (ou parte) do cliente."},
                "client_id": {"type": "integer", "description": "ID do cliente."},
                "group_name": {"type": "string", "description": "Nome (ou parte) do grupo de notas."},
                "group_id": {"type": "integer", "description": "ID do grupo de notas."},
                "search": {"type": "string", "description": "Palavra-chave no título ou conteúdo da nota."},
                "period": {"type": "string", "enum": ["all", "today", "week", "month"]},
                "pinned_only": {"type": "boolean", "description": "Retornar apenas notas fixadas."},
                "limit": {"type": "integer", "description": "Máximo de notas (padrão: 30, máx: 100)."},
            },
        },
    },
    {
        "name": "kanbandoo_get_note",
        "description": "Lê o conteúdo completo de uma nota específica pelo ID.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "note_id": {"type": "integer", "description": "ID da nota."},
            },
            "required": ["note_id"],
        },
    },
    {
        "name": "kanbandoo_create_note",
        "description": "Cria uma nova nota na aba Notas & Conhecimento do KanbanDoo (atas, diretrizes, briefings, alinhamentos).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "title": {"type": "string", "description": "Título da nota (obrigatório)."},
                "content": {"type": "string", "description": "Conteúdo completo da nota."},
                "client_name": {"type": "string", "description": "Nome do cliente vinculado."},
                "client_id": {"type": "integer", "description": "ID do cliente vinculado."},
                "group_name": {"type": "string", "description": "Nome do grupo/pasta (ex: ATAS DE REUNIOES)."},
                "group_id": {"type": "integer", "description": "ID do grupo/pasta."},
                "is_pinned": {"type": "boolean", "description": "Fixar nota no topo (padrão: false)."},
                "color": {"type": "string", "description": "Cor hexadecimal opcional."},
            },
            "required": ["title", "content"],
        },
    },
    {
        "name": "kanbandoo_update_note",
        "description": "Atualiza uma nota existente. Use 'append_content' para acrescentar texto ao final sem apagar o conteúdo anterior, ou 'content' para substituir.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "note_id": {"type": "integer", "description": "ID da nota."},
                "title": {"type": "string"},
                "content": {"type": "string", "description": "Substitui o conteúdo inteiro da nota."},
                "append_content": {"type": "string", "description": "Acrescenta este texto ao final da nota existente."},
                "client_name": {"type": "string"},
                "client_id": {"type": "integer"},
                "group_name": {"type": "string"},
                "group_id": {"type": "integer"},
                "is_pinned": {"type": "boolean"},
            },
            "required": ["note_id"],
        },
    },
    {
        "name": "kanbandoo_delete_note",
        "description": "Exclui uma nota do KanbanDoo (soft-delete).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "note_id": {"type": "integer", "description": "ID da nota."},
            },
            "required": ["note_id"],
        },
    },
]

TOOL_ACTION_MAP = {t["name"]: t["name"].replace("kanbandoo_", "", 1) for t in TOOLS}


def send_message(msg: Dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(msg, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def handle_request(req: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    req_id = req.get("id")
    method = req.get("method", "")
    params = req.get("params") or {}

    # Notificações (sem id) não devem receber resposta
    if method.startswith("notifications/"):
        return None

    if method == "initialize":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "protocolVersion": PROTOCOL_VERSION,
                "capabilities": {"tools": {"listChanged": False}},
                "serverInfo": SERVER_INFO,
            },
        }

    if method == "ping":
        return {"jsonrpc": "2.0", "id": req_id, "result": {}}

    if method == "tools/list":
        return {"jsonrpc": "2.0", "id": req_id, "result": {"tools": TOOLS}}

    if method == "tools/call":
        tool_name = params.get("name", "")
        arguments = params.get("arguments") or {}
        action = TOOL_ACTION_MAP.get(tool_name)
        if not action:
            return {
                "jsonrpc": "2.0",
                "id": req_id,
                "result": {
                    "content": [{"type": "text", "text": f"Ferramenta desconhecida: {tool_name}"}],
                    "isError": True,
                },
            }

        try:
            result = call_kanbandoo_api(action, arguments)
            is_err = not bool(result.get("ok", False))
            return {
                "jsonrpc": "2.0",
                "id": req_id,
                "result": {
                    "content": [{"type": "text", "text": json.dumps(result, ensure_ascii=False, indent=2)}],
                    "isError": is_err,
                },
            }
        except Exception as e:
            return {
                "jsonrpc": "2.0",
                "id": req_id,
                "result": {
                    "content": [{"type": "text", "text": f"Erro ao executar {tool_name}: {e}"}],
                    "isError": True,
                },
            }

    if req_id is not None:
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "error": {"code": -32601, "message": f"Method not found: {method}"},
        }
    return None


def main() -> None:
    if len(sys.argv) > 1 and sys.argv[1] == "--test":
        res = call_kanbandoo_api("whoami")
        print(json.dumps(res, ensure_ascii=False, indent=2))
        sys.exit(0 if res.get("ok") else 1)

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            req = json.loads(line)
        except json.JSONDecodeError:
            continue
        resp = handle_request(req)
        if resp is not None:
            send_message(resp)


if __name__ == "__main__":
    main()
